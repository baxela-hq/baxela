<?php

namespace Modules\Auth\Console\Commands;

use Illuminate\Console\Command;
use Modules\Auth\Actions\SyncPermissionsAction;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'auth:sync-permissions';

    protected $description = 'Create a permission for every admin route name (idempotent)';

    public function handle(SyncPermissionsAction $sync): int
    {
        $created = $sync();

        $this->info("Created {$created} permission(s).");

        return self::SUCCESS;
    }
}
