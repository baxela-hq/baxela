<?php

namespace Modules\Catalog\Search;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;

/**
 * Worker-heartbeat row management (postgres-migration §3.2.2): one row per
 * live worker process, keyed by the per-process instance token. Refresh
 * points: gate `enter()` at depth 0 (via upsert), the reindex/drain loop,
 * writer-lease touches, and the Octane request listener. Staleness is a
 * PRE-FILTER for reclamation — never proof of death on its own.
 */
final class SearchWorkerHeartbeat
{
    /**
     * Insert-or-refresh this process's heartbeat row (idempotent).
     *
     * @param  array<string, int|string>|null  $identity
     */
    public static function upsert(?array $identity = null): void
    {
        $identity ??= OwnerIncarnation::current();

        try {
            $inserted = DB::table(SearchCoordinationSchema::WORKER_HEARTBEATS_TABLE)
                ->updateOrInsert(
                    [SearchCoordinationSchema::INSTANCE_TOKEN => $identity['instance_token']],
                    [
                        SearchCoordinationSchema::WORKER_OWNER => $identity['owner'],
                        SearchCoordinationSchema::INSTANCE_BOOT_ID => $identity['boot_id'],
                        SearchCoordinationSchema::CONTAINER_ID => $identity['container_id'],
                        SearchCoordinationSchema::PID => $identity['pid'],
                        SearchCoordinationSchema::PROCESS_START_TIME => $identity['process_start_time'],
                        SearchCoordinationSchema::HEARTBEAT_AT => now(),
                    ],
                );

            unset($inserted);
        } catch (QueryException) {
            // The heartbeat table is absent (e.g. a test plane that only
            // migrated a subset): liveness reconciliation degrades to
            // fail-closed waiting, which the bounded drain handles.
        }
    }

    /** Touch only the timestamp — the cheap per-iteration refresh. */
    public static function touch(): void
    {
        try {
            DB::table(SearchCoordinationSchema::WORKER_HEARTBEATS_TABLE)
                ->where(SearchCoordinationSchema::INSTANCE_TOKEN, OwnerIncarnation::instanceToken())
                ->update([SearchCoordinationSchema::HEARTBEAT_AT => now()]);
        } catch (QueryException) {
            // Absent table: see upsert().
        }
    }

    /**
     * Whether the given worker's heartbeat is fresh (the liveness half of
     * lease-claim gating and reclamation pre-filtering).
     */
    public static function isFresh(string $instanceToken, int $staleAfterSeconds = 60): bool
    {
        $heartbeatAt = DB::table(SearchCoordinationSchema::WORKER_HEARTBEATS_TABLE)
            ->where(SearchCoordinationSchema::INSTANCE_TOKEN, $instanceToken)
            ->value(SearchCoordinationSchema::HEARTBEAT_AT);

        return $heartbeatAt !== null
            && now()->diffInSeconds($heartbeatAt, absolute: true) < $staleAfterSeconds;
    }

    /**
     * Drop heartbeat rows of workers that stopped reporting long ago and
     * hold nothing open (reconciler maintenance). A worker gets a new row
     * on every process start, so without this the table grows for the
     * life of the deployment.
     *
     * Rows with an OPEN admission are kept: the drain's reclamation only
     * considers admissions joined to a heartbeat row, so removing one
     * would strand its admission fail-closed forever. A live worker whose
     * row was dropped simply gets a new one on its next depth-0 gate
     * entry (upsert), inside the same transaction as its admission.
     *
     * @return int the number of rows removed
     */
    public static function prune(int $keepHours = 24): int
    {
        $heartbeats = SearchCoordinationSchema::WORKER_HEARTBEATS_TABLE;
        $admissions = SearchCoordinationSchema::ADMISSIONS_TABLE;

        return DB::table($heartbeats)
            ->where($heartbeats.'.'.SearchCoordinationSchema::HEARTBEAT_AT, '<', now()->subHours($keepHours))
            ->whereNotExists(function ($query) use ($heartbeats, $admissions): void {
                $query->selectRaw('1')
                    ->from($admissions.' as a')
                    ->whereColumn('a.'.SearchCoordinationSchema::INSTANCE_TOKEN, $heartbeats.'.'.SearchCoordinationSchema::INSTANCE_TOKEN)
                    ->whereNull('a.'.SearchCoordinationSchema::RELEASED_AT);
            })
            ->delete();
    }
}
