<?php

namespace Modules\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;
use Modules\Catalog\Search\OwnerIncarnation;
use Modules\Catalog\Search\SearchGenerationGuard;

/**
 * Operator resolution for ambiguous operations (v21 F1). The menu is
 * split by the TARGET UID's state, and there is no risk-acceptance
 * override for a serving target:
 *
 *  - target uid SERVING → the tool REFUSES: a delayed request could
 *    still mutate serving data after newer writes. The only safe paths
 *    are owner self-resolution with proven pre-submit abandonment, or a
 *    generation bump (`catalog:reindex`).
 *  - target uid RETIRED (a later generation durably serves) → the
 *    operation may be unblocked WITHOUT claiming remote terminal
 *    completion (task_status = 'unresolved_remote'); the retired uid's
 *    deletion stays subject to quiescence, and a late task landing on it
 *    is inert garbage the reaper removes.
 *
 * Owner-incarnation death proof is verified fail-closed for ambiguous
 * rows: unreadable /proc or an unknown container refuses resolution.
 */
final class ResolveSearchIntentCommand extends Command
{
    protected $signature = 'catalog:search:resolve-intent {operationId}';

    protected $description = 'Resolve an ambiguous search operation (retired-target only; serving targets are refused)';

    public function __construct(private readonly SearchGenerationGuard $guard)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $operationId = (int) $this->argument('operationId');

        $operation = DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
            ->where('id', $operationId)
            ->first();

        if ($operation === null) {
            $this->error("Operation {$operationId} not found.");

            return self::FAILURE;
        }

        $targetUid = (string) $operation->{SearchCoordinationSchema::TARGET_INDEX_UID};
        $serving = $this->guard->servingState();

        if ($targetUid === $serving['uid']) {
            $this->error(sprintf(
                'Operation %d targets the SERVING uid (%s). A delayed request could still mutate serving data after newer writes, so evidence or acknowledgement cannot resolve it. Safe paths: owner self-resolution with proven pre-submit abandonment, or a generation bump (`catalog:reindex`).',
                $operationId,
                $targetUid,
            ));

            return self::FAILURE;
        }

        if ($operation->{SearchCoordinationSchema::TASK_UID} !== null) {
            $this->error(sprintf(
                'Operation %d has a persisted task UID (%s, status %s) — await it via catalog:search:cleanup; no manual resolution needed.',
                $operationId,
                $operation->{SearchCoordinationSchema::TASK_UID},
                (string) $operation->{SearchCoordinationSchema::TASK_STATUS},
            ));

            return self::FAILURE;
        }

        // Owner-incarnation death proof, fail closed: an alive worker may
        // still be mid-flight toward submission (check-to-submit window),
        // and /proc unreadable means we cannot prove death.
        $incarnation = [
            'boot_id' => (string) $operation->{SearchCoordinationSchema::BOOT_ID},
            'pid' => (int) $operation->{SearchCoordinationSchema::PID},
            'process_start_time' => (string) $operation->{SearchCoordinationSchema::PROCESS_START_TIME},
        ];

        if (! OwnerIncarnation::provenDead($incarnation)) {
            $this->error(sprintf(
                'Cannot verify that the owning worker incarnation (pid %d, start %s) is dead. The worker may resume and submit to the retired uid — harmless for serving, but deletion stays blocked. Kill the worker (or wait for it to finish) and retry; then the uid cleanup proceeds.',
                $incarnation['pid'],
                $incarnation['process_start_time'] ?: 'unknown',
            ));

            return self::FAILURE;
        }

        if (! $this->confirm(
            "Resolve operation {$operationId} against RETIRED uid {$targetUid}? This releases the product's uniqueness key WITHOUT claiming the remote task was terminal; a late task may recreate the retired index as an unserved ghost (the reaper removes it).",
        )) {
            $this->line('Aborted.');

            return self::FAILURE;
        }

        DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
            ->where('id', $operationId)
            ->whereNull(SearchCoordinationSchema::RESOLVED_AT)
            ->update([
                SearchCoordinationSchema::TASK_STATUS => 'unresolved_remote',
                SearchCoordinationSchema::RESOLVED_AT => now(),
                SearchCoordinationSchema::DOC_KEY => null,
            ]);

        $this->info("Operation {$operationId} resolved (remote outcome honestly unresolved; product unblocked).");

        return self::SUCCESS;
    }
}
