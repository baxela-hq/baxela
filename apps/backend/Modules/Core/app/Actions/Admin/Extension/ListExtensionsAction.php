<?php

namespace Modules\Core\Actions\Admin\Extension;

use Modules\Core\Extensions\ComposerPackages;
use Modules\Core\Extensions\ExtensionManager;
use Modules\Core\Schemas\Extension\ExtensionSchema;

/**
 * Rows plus live composer facts, for the admin extensions surface
 * (roadmap Phase 5 builds the page on this). Read-only on purpose —
 * enabling/disabling is CLI-only until Phase 5.
 */
class ListExtensionsAction
{
    public function __construct(
        protected ExtensionManager $manager,
        protected ComposerPackages $packages,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        $slugs = collect(array_keys($this->manager->all()))
            ->merge($this->packages->extensionSlugs())
            ->unique()
            ->sort()
            ->values();

        return $slugs
            ->map(fn (string $slug): array => [
                ExtensionSchema::SLUG => $slug,
                ExtensionSchema::ENABLED => $this->manager->enabled($slug),
                ExtensionSchema::RES_INSTALLED => $this->packages->package($slug) !== null,
                ExtensionSchema::RES_VERSION => $this->packages->version($slug),
                ExtensionSchema::RES_SKIP_REASON => $this->skipReason($slug),
            ])
            ->all();
    }

    protected function skipReason(string $slug): ?string
    {
        if ($this->manager->skipReason($slug) !== null) {
            return $this->manager->skipReason($slug);
        }

        if ($this->manager->row($slug) === null) {
            return 'missing_row';
        }

        if ($this->packages->package($slug) === null) {
            return 'not_installed';
        }

        return null;
    }
}
