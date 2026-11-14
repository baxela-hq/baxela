<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Actions\Admin\Extension\CheckExtensionUpdatesAction;
use Modules\Core\Console\Commands\Concerns\InteractsWithComposer;
use Modules\Core\Extensions\ComposerPackages;
use Modules\Core\Extensions\ExtensionManager;

/**
 * baxela:remove — disable first (the provider's guarded boot no-ops),
 * give the package its uninstall() hook while it is still in vendor,
 * then let composer take it out and drop the row. A failed composer
 * remove keeps the (disabled) row so the state stays recorded.
 */
class BaxelaRemoveCommand extends Command
{
    use InteractsWithComposer;

    protected $signature = 'baxela:remove {package}';

    protected $description = 'Remove a baxela extension: disable it, run its uninstall hook, composer remove, drop its row';

    public function handle(ExtensionManager $manager, ComposerPackages $packages): int
    {
        $slug = (string) $this->argument('package');

        if (! $this->validSlug($slug)) {
            return self::FAILURE;
        }

        $row = $manager->row($slug);
        $package = $packages->package($slug);

        if ($row === null && $package === null) {
            $this->error("[{$slug}] is not installed.");

            return self::FAILURE;
        }

        if ($row !== null) {
            $manager->disable($slug);
        }

        // The hook runs while the package is still on disk — it may
        // clean up rows and files composer knows nothing about.
        $providerClass = $packages->providerFor($slug);

        if ($providerClass !== null) {
            $this->resolveExtensionProvider($providerClass)?->uninstall();
        }

        if ($package !== null) {
            $this->info("Removing {$slug}…");

            if (! $this->composer(['remove', $slug])) {
                $this->error('composer remove failed; the disabled row was kept so the state stays recorded.');

                return self::FAILURE;
            }

            // The installed package set changed (P5.3) — invalidate now,
            // before the row cleanup, whatever happens next.
            CheckExtensionUpdatesAction::flush();
        }

        $manager->remove($slug);
        ExtensionManager::flush();

        $this->info("Removed {$slug}.");

        return self::SUCCESS;
    }
}
