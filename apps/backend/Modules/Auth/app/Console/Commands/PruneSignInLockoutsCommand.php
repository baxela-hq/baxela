<?php

namespace Modules\Auth\Console\Commands;

use Illuminate\Console\Command;
use Modules\Auth\Models\SignInLockout;
use Modules\Auth\Schemas\SignInLockout\SignInLockoutSchema;
use Modules\Auth\Support\SignInLockout\SignInLockoutService;

class PruneSignInLockoutsCommand extends Command
{
    protected $signature = 'auth:prune-sign-in-lockouts';

    protected $description = 'Delete inert sign-in lockout rows (lock expired and failure counter stale)';

    public function handle(): int
    {
        $now = now();
        $retentionCutoff = $now->copy()->subDays(SignInLockoutService::PRUNE_AFTER_DAYS);

        // Inert = no active lock AND nothing fresh enough to matter. The
        // inactivity-reset window equals lockout_minutes, so rows whose
        // lock has passed go stale well inside the retention window.
        $deleted = SignInLockout::query()
            ->where(function ($query) use ($now): void {
                $query
                    ->whereNull(SignInLockoutSchema::LOCKED_UNTIL)
                    ->orWhere(SignInLockoutSchema::LOCKED_UNTIL, '<=', $now);
            })
            ->where(function ($query) use ($retentionCutoff): void {
                $query
                    ->whereNull(SignInLockoutSchema::LAST_FAILED_AT)
                    ->orWhere(SignInLockoutSchema::LAST_FAILED_AT, '<', $retentionCutoff);
            })
            ->delete();

        $this->info("Pruned {$deleted} inert sign-in lockout row(s).");

        return self::SUCCESS;
    }
}
