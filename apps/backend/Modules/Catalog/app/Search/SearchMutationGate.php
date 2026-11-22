<?php

namespace Modules\Catalog\Search;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;

/**
 * The mutation-admission barrier (review v8/v11,
 * postgres-migration §3.2): product/variant write sites call `enter()`
 * before opening their transaction and `exit()` in `finally`. Admission
 * is committed (autocommit) BEFORE the mutation transaction opens, via an
 * atomic gate-row handshake:
 *
 *   admission = tx { gate row FOR UPDATE; verify open; INSERT admission;
 *                    upsert worker heartbeat; }
 *
 * so a mutation can never slip between a gate check and the cutover's
 * close (both serialize on the same row lock — review v8 F1). Reentrant:
 * nested mutation scopes (importers calling product actions, the seeder
 * calling CreateProductAction) reuse the outer admission — a nested
 * `enter()` can never block while holding an admitted transaction open
 * (review v6 F1).
 *
 * `drain()` waits until every admission is released — release happens in
 * `finally`, strictly after commit/rollback, so equality proves zero
 * in-flight mutation transactions. Never guess from MAX(id).
 *
 * Dead-admission reclamation is driver-agnostic (§3.2.1): an open
 * admission is reclaimable only when BOTH its worker's heartbeat is
 * stale AND OwnerIncarnation::provenDead certifies the exact process is
 * dead (/proc evidence, topology-scoped). A stale heartbeat alone is
 * never proof — a paused-but-alive worker (readable /proc, matching
 * start times) is never reclaimed; unprovable cases fail closed and the
 * bounded drain aborts the cutover safely.
 */
final class SearchMutationGate
{
    /** Per-process reentrancy depth + the depth-0 admission row id. */
    private static int $depth = 0;

    private static ?int $admissionId = null;

    public function __construct(
        private readonly SearchGenerationGuard $guard,
        private readonly int $drainTimeoutSeconds = 30,
        private readonly int $workerStaleAfterSeconds = 60,
    ) {}

    /**
     * @throws SearchGateClosedException when the gate is closed (cutover in
     *                                   progress) — the caller aborts before
     *                                   ANY database work; nothing is held.
     */
    public function enter(): void
    {
        if (self::$depth > 0) {
            self::$depth++;

            return;
        }

        $this->handshake();
        self::$depth = 1;
    }

    public function exit(): void
    {
        if (self::$depth === 0) {
            return;
        }

        self::$depth--;

        if (self::$depth === 0 && self::$admissionId !== null) {
            // Token-guarded release (§3.2): a resumed zombie's release
            // no-ops — only the admitting instance can release.
            DB::table(SearchCoordinationSchema::ADMISSIONS_TABLE)
                ->where('id', self::$admissionId)
                ->where(SearchCoordinationSchema::INSTANCE_TOKEN, OwnerIncarnation::instanceToken())
                ->whereNull(SearchCoordinationSchema::RELEASED_AT)
                ->update([SearchCoordinationSchema::RELEASED_AT => now()]);
            self::$admissionId = null;
        }
    }

    /**
     * Cutover barrier wait: block until every admitted mutation has
     * committed or rolled back (release is strictly post-transaction).
     * Bounded — a timeout throws and the cutover aborts safely.
     */
    public function drain(): void
    {
        $deadline = now()->addSeconds($this->drainTimeoutSeconds);

        do {
            $inFlight = DB::table(SearchCoordinationSchema::ADMISSIONS_TABLE)
                ->whereNull(SearchCoordinationSchema::RELEASED_AT)
                ->count();

            if ($inFlight === 0) {
                return;
            }

            $this->reclaimDeadAdmissions();

            usleep(250_000);
        } while (now()->lt($deadline));

        throw new SearchGateClosedException('Admission drain timed out; in-flight mutations did not settle.');
    }

    /** Refresh this worker's liveness row (§3.2.2 heartbeat lifecycle). */
    public function heartbeat(): void
    {
        SearchWorkerHeartbeat::touch();
    }

    private function handshake(): void
    {
        $identity = OwnerIncarnation::current();
        $generation = $this->guard->servingState()['generation'];

        DB::transaction(function () use ($identity, $generation): void {
            $gate = DB::table(SearchCoordinationSchema::GATE_TABLE)
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if ((string) $gate->{SearchCoordinationSchema::STATE} !== SearchCoordinationSchema::STATE_OPEN) {
                throw new SearchGateClosedException('The search mutation gate is closed (cutover in progress).');
            }

            static::$admissionId = DB::table(SearchCoordinationSchema::ADMISSIONS_TABLE)->insertGetId([
                SearchCoordinationSchema::OWNER => $identity['owner'],
                SearchCoordinationSchema::INSTANCE_TOKEN => $identity['instance_token'],
                SearchCoordinationSchema::BOOT_ID => $identity['boot_id'],
                SearchCoordinationSchema::CONTAINER_ID => $identity['container_id'],
                SearchCoordinationSchema::CONTAINER_STARTED_AT => $identity['container_started_at'],
                SearchCoordinationSchema::PID => $identity['pid'],
                SearchCoordinationSchema::PROCESS_START_TIME => $identity['process_start_time'],
                SearchCoordinationSchema::GENERATION => $generation,
                SearchCoordinationSchema::ACQUIRED_AT => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Liveness anchor for this admission: upsert on every depth-0
            // enter (§3.2.2 refresh point (a)).
            SearchWorkerHeartbeat::upsert($identity);
        });
    }

    /**
     * Reclaim admissions whose owning worker is provably dead (§3.2.1):
     * stale heartbeat AND /proc incarnation proof — the dead worker's
     * transaction was rolled back server-side, so reclaiming can never
     * race a live transaction. Everything else fails closed (stale
     * heartbeat alone, cross-container unprovability, unreadable /proc):
     * the admission stays and the bounded drain decides.
     */
    private function reclaimDeadAdmissions(): void
    {
        $staleCutoff = now()->subSeconds($this->workerStaleAfterSeconds);

        $candidates = DB::table(SearchCoordinationSchema::ADMISSIONS_TABLE.' AS a')
            ->join(
                SearchCoordinationSchema::WORKER_HEARTBEATS_TABLE.' AS h',
                'a.'.SearchCoordinationSchema::INSTANCE_TOKEN,
                '=',
                'h.'.SearchCoordinationSchema::INSTANCE_TOKEN,
            )
            ->whereNull('a.'.SearchCoordinationSchema::RELEASED_AT)
            ->where('h.'.SearchCoordinationSchema::HEARTBEAT_AT, '<', $staleCutoff)
            ->get([
                'a.id AS id',
                'a.'.SearchCoordinationSchema::BOOT_ID.' AS boot_id',
                'a.'.SearchCoordinationSchema::CONTAINER_ID.' AS container_id',
                'a.'.SearchCoordinationSchema::PID.' AS pid',
                'a.'.SearchCoordinationSchema::PROCESS_START_TIME.' AS process_start_time',
            ]);

        $dead = [];
        foreach ($candidates as $candidate) {
            if (OwnerIncarnation::provenDead((array) $candidate)) {
                $dead[] = $candidate->id;
            }
        }

        if ($dead !== []) {
            DB::table(SearchCoordinationSchema::ADMISSIONS_TABLE)
                ->whereIn('id', $dead)
                ->whereNull(SearchCoordinationSchema::RELEASED_AT)
                ->update([
                    SearchCoordinationSchema::RELEASED_AT => now(),
                ]);
        }
    }
}
