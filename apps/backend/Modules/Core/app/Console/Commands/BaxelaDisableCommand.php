<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Console\Commands\Concerns\InteractsWithComposer;
use Modules\Core\Extensions\ExtensionManager;

/**
 * baxela:disable — the runtime kill switch. Flips the row; on the next
 * request the guard skips the provider, its drivers leave the registry,
 * and its methods disappear from checkout without touching core or
 * composer. Works on leftover rows too (disabling what is already
 * inert is harmless and explicit).
 */
class BaxelaDisableCommand extends Command
{
    use InteractsWithComposer;

    protected $signature = 'baxela:disable {package}';

    protected $description = 'Disable an installed baxela extension at runtime';

    public function handle(ExtensionManager $manager): int
    {
        $slug = (string) $this->argument('package');

        if (! $this->validSlug($slug)) {
            return self::FAILURE;
        }

        if ($manager->row($slug) === null) {
            $this->error("[{$slug}] is not installed.");

            return self::FAILURE;
        }

        $manager->disable($slug);
        ExtensionManager::flush();

        $this->info("Disabled {$slug}.");

        return self::SUCCESS;
    }
}
