<?php

namespace Modules\Core\Extensions;

use Illuminate\Support\ServiceProvider;

/**
 * Base class every Baxela extension's service provider extends. The
 * final boot() runs the three fail-closed guards through
 * ExtensionManager — missing row, disabled, compat mismatch — and only
 * on pass runs the extension's registrations: a skipped extension is
 * inert (no drivers merged, no bindings), never fatal.
 *
 * register() is deliberately final and empty: Laravel runs every
 * register() before any boot(), so the DB-dependent guard cannot run
 * there, and an unguarded config merge would leak a disabled driver
 * into the payment registry.
 */
abstract class Extension extends ServiceProvider
{
    /**
     * Runtime manifest — the runtime truth the guards check. Mirrors
     * the composer extra.baxela metadata (which is for distribution and
     * marketplace validation): slug, name, type, version, and
     * compat => ['core' => '^1.0'].
     *
     * @return array<string, mixed>
     */
    abstract public function manifest(): array;

    /**
     * The extension's registrations — driver config merges, bindings,
     * listeners. Runs only when every guard passes.
     */
    protected function extensionBoot(): void {}

    /**
     * Hook from baxela:install, after migrations ran and the enabled
     * row exists — seed whatever the extension needs.
     */
    public function install(): void {}

    /**
     * Hook from baxela:remove, before the package leaves vendor —
     * clean up what composer cannot.
     */
    public function uninstall(): void {}

    final public function register(): void {}

    final public function boot(): void
    {
        $manifest = $this->manifest();

        if (! app(ExtensionManager::class)->shouldBoot((string) $manifest['slug'], $manifest)) {
            return;
        }

        $this->extensionBoot();
    }
}
