<?php

namespace Modules\Core\Console\Commands;

use App\Baxela;
use Illuminate\Console\Command;
use Modules\Core\Console\Commands\Concerns\InteractsWithComposer;
use Modules\Core\Extensions\ComposerPackages;
use Modules\Core\Extensions\ExtensionManager;

/**
 * baxela:install — the front door for extensions (extension plan §4
 * decision 6). Composer owns resolution; this command owns the
 * invariants: only baxela-extension packages, compat checked before
 * anything persists, and a failed require leaves no row behind.
 */
class BaxelaInstallCommand extends Command
{
    use InteractsWithComposer;

    protected $signature = 'baxela:install {package} {--ignore-compat}';

    protected $description = 'Install a baxela extension: composer require, migrate, register its provider, enable it';

    public function handle(ExtensionManager $manager, ComposerPackages $packages): int
    {
        $slug = (string) $this->argument('package');

        if (! $this->validSlug($slug)) {
            return self::FAILURE;
        }

        if ($manager->row($slug) !== null && $packages->package($slug) !== null) {
            $this->info("{$slug} is already installed.");

            return self::SUCCESS;
        }

        $this->info("Requiring {$slug}…");

        if (! $this->composer(['require', $slug])) {
            // Composer's own output (stderr included) has streamed
            // verbatim; nothing has been recorded to clean up.
            return self::FAILURE;
        }

        if (! $packages->isExtensionPackage($slug)) {
            $this->rollback($slug, "refused: [{$slug}] is not of type baxela-extension");

            return self::FAILURE;
        }

        if (! $packages->compatible($slug)) {
            $required = (string) ($packages->manifestFor($slug)['compat']['core'] ?? '?');

            if (! $this->option('ignore-compat')) {
                $this->rollback($slug, "refused: [{$slug}] requires core {$required}, this core is ".Baxela::VERSION);

                return self::FAILURE;
            }

            $this->warn('Compat mismatch ignored (--ignore-compat); the runtime guard keeps it inert until core or the extension updates.');
        }

        $this->call('migrate', ['--force' => true]);

        $manager->install($slug);

        $providerClass = $packages->providerFor($slug);

        if ($providerClass === null) {
            $this->warn('No provider extending Baxela\Extension declared; the row exists but nothing boots.');
        } else {
            $this->resolveExtensionProvider($providerClass)?->install();
        }

        ExtensionManager::flush();

        $version = $packages->version($slug) ?? '?';
        $this->info("Installed {$slug} ({$version}) and enabled it.");

        return self::SUCCESS;
    }

    /**
     * Undo a require that must not stand: composer remove, leave no
     * row, keep the tree exactly as it was.
     */
    protected function rollback(string $slug, string $reason): void
    {
        $this->error($reason);
        $this->info("Rolling back: composer remove {$slug}…");
        $this->composer(['remove', $slug]);
    }
}
