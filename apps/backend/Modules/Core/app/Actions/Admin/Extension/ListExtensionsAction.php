<?php

namespace Modules\Core\Actions\Admin\Extension;

use Modules\Core\Extensions\ComposerPackages;
use Modules\Core\Extensions\ExtensionManager;
use Modules\Core\Extensions\ExtensionStateBuilder;

/**
 * Rows plus live composer facts, for the admin extensions surface. Each
 * slug's state comes from ExtensionStateBuilder — derived from current
 * rows + Composer, never from boot-recorded diagnostics, so the list
 * reflects a runtime toggle immediately and stays honest under Octane.
 */
class ListExtensionsAction
{
    public function __construct(
        protected ExtensionManager $manager,
        protected ComposerPackages $packages,
        protected ExtensionStateBuilder $builder,
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
            ->map(fn (string $slug): array => $this->builder->build($slug))
            ->all();
    }
}
