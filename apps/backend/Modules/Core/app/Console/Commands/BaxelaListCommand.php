<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Extensions\ComposerPackages;
use Modules\Core\Extensions\ExtensionManager;
use Modules\Core\Schemas\Extension\ExtensionSchema;

/**
 * baxela:list — every extension from both sources of truth: rows the
 * commands manage, and packages in vendor that declare themselves
 * baxela-extension without a row (side-loaded). Version comes live
 * from composer; the skip reason comes from the boot guard, so an
 * inert extension always says why.
 *
 * Extensions are not modules: they are composer packages under vendor/
 * booted through Modules\Core\Extensions\Extension, and never touch
 * modules_statuses.json or Modules/.
 */
class BaxelaListCommand extends Command
{
    protected $signature = 'baxela:list';

    protected $description = 'List installed baxela extensions with version, enabled state and skip reason';

    public function handle(ExtensionManager $manager, ComposerPackages $packages): int
    {
        $rows = collect(array_keys($manager->all()))
            ->merge($packages->extensionSlugs())
            ->unique()
            ->sort()
            ->map(fn (string $slug): array => [
                'slug' => $slug,
                'version' => $packages->version($slug) ?? '-',
                ExtensionSchema::ENABLED => $manager->enabled($slug) ? 'yes' : 'no',
                'skip reason' => $this->reason($manager, $packages, $slug) ?? '-',
            ])
            ->all();

        if ($rows === []) {
            $this->info('No baxela extensions installed.');

            return self::SUCCESS;
        }

        $this->table(['slug', 'version', 'enabled', 'skip reason'], $rows);

        return self::SUCCESS;
    }

    protected function reason(ExtensionManager $manager, ComposerPackages $packages, string $slug): ?string
    {
        if ($manager->skipReason($slug) !== null) {
            return $manager->skipReason($slug);
        }

        // Rows without their package (removed out-of-band) and packages
        // without a row (side-loaded) are both inert — say which.
        if ($manager->row($slug) === null) {
            return 'missing_row';
        }

        if ($packages->package($slug) === null) {
            return 'not_installed';
        }

        return null;
    }
}
