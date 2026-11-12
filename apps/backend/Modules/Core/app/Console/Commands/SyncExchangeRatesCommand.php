<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Actions\Admin\Currency\SyncExchangeRatesAction;
use Modules\Core\Exceptions\BaseException;
use Throwable;

/**
 * The scheduled + on-demand FX sync entry point (Part E). The sync
 * engine owns every gate; this command only maps outcomes to output
 * and exit codes.
 */
class SyncExchangeRatesCommand extends Command
{
    protected $signature = 'currencies:sync-rates {--force : Bypass the enabled/due/cooldown gates}';

    protected $description = 'Sync display exchange rates from the configured FX provider (opt-in)';

    public function handle(SyncExchangeRatesAction $action): int
    {
        try {
            $result = $action->sync(force: (bool) $this->option('force'));
        } catch (Throwable $e) {
            // Sanitized output only — error codes, never provider bodies.
            report($e);

            $code = $e instanceof BaseException ? (string) $e->getCode() : 'sync_failed';

            $this->error("FX sync failed [{$code}]. Rates were not modified.");

            return self::FAILURE;
        }

        $this->info(match ($result['outcome']) {
            SyncExchangeRatesAction::OUTCOME_SYNCED => 'Exchange rates synced successfully.',
            SyncExchangeRatesAction::OUTCOME_SYNC_IN_PROGRESS => 'Another FX sync is already running — skipped.',
            SyncExchangeRatesAction::OUTCOME_SKIPPED_DISABLED => 'FX auto-sync is disabled — skipped.',
            SyncExchangeRatesAction::OUTCOME_SKIPPED_NOT_DUE => 'FX sync is not due yet — skipped.',
            SyncExchangeRatesAction::OUTCOME_SKIPPED_COOLDOWN => 'Last FX sync failed too recently (cooldown) — skipped.',
            default => "FX sync outcome: {$result['outcome']}",
        });

        return self::SUCCESS;
    }
}
