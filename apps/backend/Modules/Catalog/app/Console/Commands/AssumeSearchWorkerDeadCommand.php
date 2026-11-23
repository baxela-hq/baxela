<?php

namespace Modules\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;
use Modules\Catalog\Search\SearchAdmissionOverride;
use Modules\Catalog\Search\SearchAdmissionOverrideResult;

/**
 * Operator last resort for a cutover blocked by admissions of a worker
 * whose death cannot be PROVEN (postgres-migration §3.2.2): release them
 * by decree.
 *
 * Deliberately narrow on purpose:
 *
 *  - `--reason` is mandatory and lands in the audit row;
 *  - the recorded incarnation is listed and must be confirmed
 *    interactively — non-interactive runs are refused;
 *  - the mutation is conditional and audited in one transaction (see
 *    {@see SearchAdmissionOverride}); a stale token releases nothing and
 *    reports `superseded`.
 *
 * Run this only after establishing, out of band, that the worker cannot
 * be running (its container is gone, the host rebooted). A worker that
 * merely paused and resumes afterwards finds itself fenced out of
 * further writes, but the drain no longer waits for its open
 * transaction.
 */
final class AssumeSearchWorkerDeadCommand extends Command
{
    protected $signature = 'catalog:search:assume-dead
        {instance_token : The worker identity shown by a stuck drain}
        {--reason= : Why the override is justified (mandatory; audited)}';

    protected $description = 'Release a search worker\'s open admissions by operator decree (audited last resort)';

    public function __construct(private readonly SearchAdmissionOverride $override)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $token = (string) $this->argument('instance_token');
        $reason = trim((string) $this->option('reason'));

        if ($reason === '') {
            $this->error('A reason is mandatory: --reason="<why this worker cannot be running>". The override is audited with it.');

            return self::FAILURE;
        }

        $heartbeat = $this->override->heartbeat($token);
        $openIds = $this->override->openAdmissionIds($token);

        if ($heartbeat === null || $openIds === null) {
            $this->override->__invoke($token, $reason, $this->operator());
            $this->error("No search worker carries instance token {$token}. Nothing was released.");

            return self::FAILURE;
        }

        $this->line('Recorded worker incarnation:');
        $this->table(['field', 'value'], [
            ['instance token', $token],
            ['boot id', $heartbeat->{SearchCoordinationSchema::INSTANCE_BOOT_ID}],
            ['container', $heartbeat->{SearchCoordinationSchema::CONTAINER_ID}],
            ['pid', $heartbeat->{SearchCoordinationSchema::PID}],
            ['process start', $heartbeat->{SearchCoordinationSchema::PROCESS_START_TIME}],
            ['last heartbeat', $heartbeat->{SearchCoordinationSchema::HEARTBEAT_AT}],
            ['open admissions', $openIds === [] ? 'none' : implode(', ', $openIds)],
        ]);

        if ($openIds === []) {
            $result = $this->override->__invoke($token, $reason, $this->operator());
            $this->warn('Nothing of this worker is open any more (already released or reclaimed) — superseded, nothing to do.');

            return $result->status === SearchAdmissionOverride::RELEASED ? self::SUCCESS : self::FAILURE;
        }

        $confirmed = $this->confirm(sprintf(
            'Release the %d open admission(s) of this worker? Only when you have verified it cannot be running — the release is recorded with your reason.',
            count($openIds),
        ));

        if (! $confirmed) {
            $this->line('Aborted — nothing was released.');

            return self::FAILURE;
        }

        $result = $this->override->__invoke($token, $reason, $this->operator());

        return $this->report($result);
    }

    private function report(SearchAdmissionOverrideResult $result): int
    {
        switch ($result->status) {
            case SearchAdmissionOverride::RELEASED:
                $this->info("Released {$result->released} admission(s). The next cutover drain no longer waits for this worker.");

                return self::SUCCESS;
            case SearchAdmissionOverride::SUPERSEDED:
                $this->warn('Nothing of this worker was open any more by the time the override ran (released or reclaimed meanwhile) — superseded, nothing to do.');

                return self::FAILURE;
            default:
                $this->error('No search worker carries this instance token. Nothing was released.');

                return self::FAILURE;
        }
    }

    /** Who is acting — the audit row's operator identity. */
    private function operator(): string
    {
        $user = function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '') : '';

        return sprintf('console:%s@%s', $user !== '' ? $user : 'uid-'.getmyuid(), gethostname() ?: 'unknown-host');
    }
}
