<?php

namespace Modules\Catalog\Search;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;
use RuntimeException;

/**
 * The single authoritative component for generation identity and the
 * serving pointer (v21 F2): every path that selects, creates, promotes,
 * or deletes an index resolves the current generation THROUGH this guard
 * — never from remote index existence, never from a cached value.
 *
 * Guarantees:
 *  - Generation allocation is monotonic under the gate-row FOR UPDATE
 *    lock; failed/abandoned builds consume their number permanently
 *    (never reused, so a late task can never collide with a recreated
 *    index).
 *  - The serving pointer is durable state — the ONLY source of truth
 *    for which generation serves (a missing serving index triggers
 *    rebuild/fallback, never promotion of an older uid).
 *  - `isRetired()` is evaluated against the durable pointer at the call
 *    site's authorization moment; the reaper re-asserts it IMMEDIATELY
 *    before every destructive action (a check earlier in a long-running
 *    cleanup job is not authorization).
 */
final class SearchGenerationGuard
{
    public function __construct(
        private readonly string $uidBase = 'catalog_products',
    ) {}

    /**
     * The durable serving state: [generation, uid, state]. Read fresh —
     * callers must treat this as authoritative for the instant of the
     * read only.
     *
     * @return array{generation: int, uid: string, state: string}
     */
    public function servingState(): array
    {
        $row = DB::table(SearchCoordinationSchema::GATE_TABLE)
            ->where('id', 1)
            ->first([
                SearchCoordinationSchema::GENERATION,
                SearchCoordinationSchema::SERVING_INDEX_UID,
                SearchCoordinationSchema::STATE,
            ]);

        if ($row === null) {
            // No gate row = the coordination migration never ran; treat as
            // the unsearchable initial state rather than guessing.
            throw new RuntimeException('Search gate row is missing — the search coordination migration has not run.');
        }

        return [
            'generation' => (int) $row->{SearchCoordinationSchema::GENERATION},
            'uid' => (string) $row->{SearchCoordinationSchema::SERVING_INDEX_UID},
            'state' => (string) $row->{SearchCoordinationSchema::STATE},
        ];
    }

    /** The uid of the generation that currently serves traffic. */
    public function servingIndexUid(): string
    {
        return $this->servingState()['uid'];
    }

    /** True when the engine integration is configured AND the gate is open. */
    public function admissionsOpen(): bool
    {
        return $this->servingState()['state'] === SearchCoordinationSchema::STATE_OPEN;
    }

    /**
     * Allocate the next build generation under the gate-row lock and
     * persist the build's ownership. Failed builds consume their number
     * permanently — a retired uid is never recreated.
     *
     * @return array{generation: int, uid: string}
     */
    public function allocateBuildGeneration(string $owner): array
    {
        return DB::transaction(function () use ($owner): array {
            $gate = DB::table(SearchCoordinationSchema::GATE_TABLE)
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            $next = (int) $gate->{SearchCoordinationSchema::NEXT_GENERATION};

            DB::table(SearchCoordinationSchema::GATE_TABLE)
                ->where('id', 1)
                ->update([
                    SearchCoordinationSchema::NEXT_GENERATION => $next + 1,
                    SearchCoordinationSchema::OWNER => $owner,
                    'updated_at' => now(),
                ]);

            return ['generation' => $next, 'uid' => $this->uidFor($next)];
        });
    }

    /**
     * The pointer flip IS the linearization point: one atomic row update.
     * Called with the build's generation — refuses to publish a stale
     * build (generation fencing at the flip itself).
     */
    public function publish(int $buildGeneration, string $uid, string $owner): void
    {
        DB::transaction(function () use ($buildGeneration, $uid): void {
            $gate = DB::table(SearchCoordinationSchema::GATE_TABLE)
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if ((int) $gate->{SearchCoordinationSchema::NEXT_GENERATION} <= $buildGeneration) {
                throw new RuntimeException(sprintf(
                    'Refusing to publish generation %d: generation state moved on (next is %d) — the build is stale.',
                    $buildGeneration,
                    (int) $gate->{SearchCoordinationSchema::NEXT_GENERATION},
                ));
            }

            DB::table(SearchCoordinationSchema::GATE_TABLE)
                ->where('id', 1)
                ->update([
                    SearchCoordinationSchema::GENERATION => $buildGeneration,
                    SearchCoordinationSchema::SERVING_INDEX_UID => $uid,
                    'updated_at' => now(),
                ]);
        });
    }

    /** Cutover barrier: close admissions (mutations block at `enter()`). */
    public function closeAdmissions(string $owner): void
    {
        DB::transaction(function () use ($owner): void {
            DB::table(SearchCoordinationSchema::GATE_TABLE)
                ->where('id', 1)
                ->lockForUpdate()
                ->update([
                    SearchCoordinationSchema::STATE => SearchCoordinationSchema::STATE_CLOSED,
                    SearchCoordinationSchema::OWNER => $owner,
                    SearchCoordinationSchema::HEARTBEAT_AT => now(),
                    'updated_at' => now(),
                ]);
        });
    }

    /** Reopen admissions after a successful (or abandoned) cutover. */
    public function openAdmissions(): void
    {
        DB::transaction(function (): void {
            DB::table(SearchCoordinationSchema::GATE_TABLE)
                ->where('id', 1)
                ->lockForUpdate()
                ->update([
                    SearchCoordinationSchema::STATE => SearchCoordinationSchema::STATE_OPEN,
                    'updated_at' => now(),
                ]);
        });
    }

    /** Reindexer/rebuilder liveness heartbeat (fencing input for the reaper). */
    public function heartbeat(int $generation, string $owner): void
    {
        DB::table(SearchCoordinationSchema::GATE_TABLE)
            ->where('id', 1)
            ->where(SearchCoordinationSchema::GENERATION, $generation)
            ->where(SearchCoordinationSchema::OWNER, $owner)
            ->update([SearchCoordinationSchema::HEARTBEAT_AT => now()]);
    }

    /**
     * A generation uid is retired when it is neither the serving uid nor
     * the one a live build is preparing (next allocation). Retired uids
     * are cleanup candidates; their tasks can never affect serving.
     */
    public function isRetired(string $uid): bool
    {
        $state = $this->servingState();

        if ($uid === $state['uid']) {
            return false;
        }

        // A uid for a generation >= next_generation belongs to no build —
        // treat as retired only if it parses as an older generation.
        return $this->generationOf($uid) !== null
            && $this->generationOf($uid) < $this->nextGeneration();
    }

    public function uidFor(int $generation): string
    {
        return $this->uidBase.'_g'.$generation;
    }

    /** @return int|null null when the uid does not parse as a generation uid. */
    public function generationOf(string $uid): ?int
    {
        if (preg_match('/^'.preg_quote($this->uidBase, '/').'_g(\d+)$/', $uid, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    private function nextGeneration(): int
    {
        $row = DB::table(SearchCoordinationSchema::GATE_TABLE)
            ->where('id', 1)
            ->first(SearchCoordinationSchema::NEXT_GENERATION);

        return $row === null ? 0 : (int) $row->{SearchCoordinationSchema::NEXT_GENERATION};
    }
}
