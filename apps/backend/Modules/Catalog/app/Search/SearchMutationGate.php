<?php

namespace Modules\Catalog\Search;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;
use Throwable;

/**
 * The mutation-admission barrier (review v8/v11): product/variant write
 * sites call `enter()` before opening their transaction and `exit()` in
 * `finally`. Admission is committed (autocommit) BEFORE the mutation
 * transaction opens, via an atomic gate-row handshake:
 *
 *   admission = tx { gate row FOR UPDATE; verify open; INSERT admission; }
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
 */
final class SearchMutationGate
{
    /** Per-process reentrancy depth + the depth-0 admission row id. */
    private static int $depth = 0;

    private static ?int $admissionId = null;

    public function __construct(
        private readonly SearchGenerationGuard $guard,
        private readonly int $drainTimeoutSeconds = 30,
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
            DB::table(SearchCoordinationSchema::ADMISSIONS_TABLE)
                ->where('id', self::$admissionId)
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

            // A still-open admission whose MySQL connection no longer exists
            // belongs to a dead process (its transaction was rolled back
            // server-side) — reclaim it; live transactions keep their
            // connection and are genuinely waited for.
            $this->reclaimDeadAdmissions();

            usleep(250_000);
        } while (now()->lt($deadline));

        throw new SearchGateClosedException('Admission drain timed out; in-flight mutations did not settle.');
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
                SearchCoordinationSchema::BOOT_ID => $identity['boot_id'],
                SearchCoordinationSchema::CONTAINER_ID => $identity['container_id'],
                SearchCoordinationSchema::CONTAINER_STARTED_AT => $identity['container_started_at'],
                SearchCoordinationSchema::PID => $identity['pid'],
                SearchCoordinationSchema::PROCESS_START_TIME => $identity['process_start_time'],
                SearchCoordinationSchema::DB_CONNECTION_ID => $identity['db_connection_id'],
                SearchCoordinationSchema::GENERATION => $generation,
                SearchCoordinationSchema::ACQUIRED_AT => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Reclaim admissions whose owner connection is gone (v11 F2): MySQL
     * rolled their transactions back when the process died, so reclaiming
     * can never race a live transaction. No-op on SQLite (no
     * connection_id; tests drive the gate explicitly).
     */
    private function reclaimDeadAdmissions(): void
    {
        try {
            $liveConnections = DB::select('select id from information_schema.processlist');
        } catch (Throwable) {
            return; // Non-MySQL test connection: nothing to reconcile.
        }

        $live = array_map(
            static fn ($row) => (int) $row->id,
            $liveConnections,
        );

        $stale = DB::table(SearchCoordinationSchema::ADMISSIONS_TABLE)
            ->whereNull(SearchCoordinationSchema::RELEASED_AT)
            ->whereNotNull(SearchCoordinationSchema::DB_CONNECTION_ID)
            ->whereNotIn(SearchCoordinationSchema::DB_CONNECTION_ID, $live === [] ? [0] : $live)
            ->pluck('id');

        if ($stale->isNotEmpty()) {
            DB::table(SearchCoordinationSchema::ADMISSIONS_TABLE)
                ->whereIn('id', $stale->all())
                ->update([SearchCoordinationSchema::RELEASED_AT => now()]);
        }
    }
}
