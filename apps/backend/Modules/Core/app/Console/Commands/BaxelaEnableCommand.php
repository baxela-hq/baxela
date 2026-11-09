<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Console\Commands\Concerns\InteractsWithComposer;
use Modules\Core\Extensions\ComposerPackages;
use Modules\Core\Extensions\ExtensionManager;

/**
 * baxela:enable — flip the row back on; on the next request the guard
 * passes and the provider boots. Refuses leftover rows whose package
 * is gone (baxela:remove owns those).
 */
class BaxelaEnableCommand extends Command
{
    use InteractsWithComposer;

    protected $signature = 'baxela:enable {package}';

    protected $description = 'Enable an installed baxela extension at runtime';

    public function handle(ExtensionManager $manager, ComposerPackages $packages): int
    {
        $slug = (string) $this->argument('package');

        if (! $this->validSlug($slug)) {
            return self::FAILURE;
        }

        if ($manager->row($slug) === null) {
            $this->error("[{$slug}] is not installed.");

            return self::FAILURE;
        }

        if ($packages->package($slug) === null) {
            $this->error("[{$slug}] is not present in vendor; remove its leftover row with baxela:remove.");

            return self::FAILURE;
        }

        $manager->enable($slug);
        ExtensionManager::flush();

        $this->info("Enabled {$slug}.");

        return self::SUCCESS;
    }
}
