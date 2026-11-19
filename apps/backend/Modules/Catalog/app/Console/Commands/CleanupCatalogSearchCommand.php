<?php

namespace Modules\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;
use Modules\Catalog\Search\OwnerIncarnation;
use Modules\Catalog\Search\SearchClientInterface;
use Modules\Catalog\Search\SearchGenerationGuard;

/**
 * Nightly retirement cleanup (v14 F3 + v18 F1): deletes retired
 * generation indexes ONLY after submission quiescence, re-validating the
 * durable serving pointer immediately before each destructive action
 * (review v21: a guard check earlier in the job is not authorization).
 *
 * A retired uid is quiescent when every operation row targeting it is
 * superseded with a determined remote outcome (task persisted + terminal,
 * or honestly 'unresolved_remote' via operator resolution). Ambiguous
 * rows (task_uid NULL, fate unknown) KEEP the uid cleanup-pending —
 * deletion is deferred, never forced; a permanently stuck row leaves the
 * old index as harmless garbage (never served, never reused).
 */
final class CleanupCatalogSearchCommand extends Command
{
    protected $signature = 'catalog:search:cleanup';

    protected $description = 'Delete retired search-generation indexes after submission quiescence (idempotent, repeatable)';

    public function __construct(
        private readonly SearchGenerationGuard $guard,
        private readonly SearchClientInterface $client,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $serving = $this->guard->servingState();

        $retiredUids = DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
            ->select(SearchCoordinationSchema::TARGET_INDEX_UID)
            ->distinct()
            ->pluck(SearchCoordinationSchema::TARGET_INDEX_UID)
            ->filter(fn ($uid) => $uid !== $serving['uid'] && $this->guard->isRetired((string) $uid));

        $deleted = 0;
        $pending = 0;

        foreach ($retiredUids as $uid) {
            // Re-validate against the durable pointer immediately before
            // the destructive action (v21 reaper-race check).
            if ($this->guard->servingState()['uid'] === $uid || ! $this->guard->isRetired($uid)) {
                continue;
            }

            if (! $this->quiescent($uid)) {
                $pending++;
                $this->line("{$uid}: cleanup-pending (ambiguous or non-terminal operations remain).");

                continue;
            }

            // Re-validate AGAIN right before the delete — the quiescence
            // scan takes time and the pointer may have moved.
            if ($this->guard->servingState()['uid'] === $uid) {
                $this->warn("{$uid}: became serving during cleanup — skipping deletion.");

                continue;
            }

            try {
                $this->client->deleteIndex($uid);
                $deleted++;
                $this->info("{$uid}: retired index deleted.");
            } catch (\Throwable $e) {
                // Deletion failure is retried by the next run — cleanup
                // must never block serving or admissions (v16 F2).
                $this->warn("{$uid}: deletion deferred ({$e->getMessage()}).");
            }
        }

        // Ghost re-creation: a late task may recreate a retired uid AFTER
        // an earlier deletion. Idempotent sweep; the next run re-cleans.
        $this->line("Retired indexes deleted: {$deleted}, still pending: {$pending}.");

        return self::SUCCESS;
    }

    /** @param  string  $uid  a retired generation uid */
    private function quiescent(string $uid): bool
    {
        $rows = DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
            ->where(SearchCoordinationSchema::TARGET_INDEX_UID, $uid)
            ->get([
                'id',
                SearchCoordinationSchema::TASK_UID,
                SearchCoordinationSchema::TASK_STATUS,
                SearchCoordinationSchema::RESOLVED_AT,
                SearchCoordinationSchema::SUPERSEDED_AT,
                SearchCoordinationSchema::OWNER,
                SearchCoordinationSchema::BOOT_ID,
                SearchCoordinationSchema::CONTAINER_ID,
                SearchCoordinationSchema::PID,
                SearchCoordinationSchema::PROCESS_START_TIME,
            ]);

        foreach ($rows as $row) {
            // An unsuperseded unresolved row targeting a retired uid should
            // not exist (the writer supersedes on retirement) — treat as
            // blocking and warn rather than delete beneath it.
            if ($row->{SearchCoordinationSchema::SUPERSEDED_AT} === null && $row->{SearchCoordinationSchema::RESOLVED_AT} === null) {
                $this->warn("{$uid}: operation {$row->id} is unsuperseded and unresolved — blocking cleanup.");

                return false;
            }

            // Superseded-ambiguous (task_uid NULL, fate unknown): blocks
            // until the operator tool resolves it (incarnation proof) or
            // the worker's fate becomes known. Timeout/lease/supersession
            // alone never clear it (v19 F1).
            if ($row->{SearchCoordinationSchema::TASK_UID} === null) {
                $this->warn("{$uid}: operation {$row->id} is superseded-ambiguous (no task UID) — blocking cleanup; use catalog:search:resolve-intent after verifying the worker incarnation.");

                return false;
            }

            // Known task: must be terminal. Still-processing blocks.
            $status = $row->{SearchCoordinationSchema::TASK_STATUS};

            if ($status === null || in_array($status, ['enqueued', 'processing'], true)) {
                $remote = $this->client->taskStatus((string) $row->{SearchCoordinationSchema::TASK_UID});

                if ($remote !== null && ! in_array($remote, ['processed', 'failed', 'canceled'], true)) {
                    $this->warn("{$uid}: task {$row->{SearchCoordinationSchema::TASK_UID}} is {$remote} — blocking cleanup.");

                    return false;
                }

                if ($remote === null) {
                    $this->warn("{$uid}: task {$row->{SearchCoordinationSchema::TASK_UID}} status undeterminable — blocking cleanup.");

                    return false;
                }

                DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
                    ->where('id', $row->id)
                    ->update([SearchCoordinationSchema::TASK_STATUS => $remote]);
            }

            // Owner-death proof (v12 F1) — necessary context for operator
            // resolution, never sufficient alone for a UID-less row (v20).
            // For known-UID rows the terminal observation above IS the
            // remote proof; for ambiguous rows the tool refuses until this
            // plus the explicit operator confirmation.
            if (isset($row->{SearchCoordinationSchema::OWNER}) && ! OwnerIncarnation::provenDead([
                'boot_id' => $row->{SearchCoordinationSchema::BOOT_ID} ?? '',
                'pid' => $row->{SearchCoordinationSchema::PID} ?? 0,
                'process_start_time' => $row->{SearchCoordinationSchema::PROCESS_START_TIME} ?? '',
            ])) {
                $this->line("{$uid}: operation {$row->id} owner incarnation may still be alive (informational).");
            }
        }

        return true;
    }
}
