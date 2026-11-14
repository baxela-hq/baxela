<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Actions\Admin\Extension\CheckExtensionUpdatesAction;
use Modules\Core\Console\Commands\Concerns\InteractsWithComposer;
use Modules\Core\Extensions\ComposerPackages;
use Modules\Core\Extensions\ExtensionManager;

/**
 * baxela:update — composer owns the resolution, this command owns the
 * migrations and the cache bust. No downgrade support (roadmap policy
 * §5.3): composer update only moves a package up. Version is never
 * stored — it is read live, so there is no row to refresh.
 */
class BaxelaUpdateCommand extends Command
{
    use InteractsWithComposer;

    protected $signature = 'baxela:update {package}';

    protected $description = 'Update a baxela extension: composer update the package, run new migrations';

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

        $before = $packages->version($slug);

        $this->info("Updating {$slug}…");

        if (! $this->composer(['update', $slug])) {
            return self::FAILURE;
        }

        // The installed package set changed (P5.3) — invalidate before
        // migrations run, whatever happens next.
        CheckExtensionUpdatesAction::flush();

        $this->call('migrate', ['--force' => true]);

        if ($packages->package($slug) !== null && ! $packages->compatible($slug)) {
            $this->warn('The updated package no longer satisfies this core; the runtime guard keeps it inert.');
        }

        ExtensionManager::flush();

        $this->info("Updated {$slug}".($before !== null ? " ({$before} → ".($packages->version($slug) ?? '?').')' : ''));

        return self::SUCCESS;
    }
}
