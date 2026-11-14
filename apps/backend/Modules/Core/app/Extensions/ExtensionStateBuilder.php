<?php

namespace Modules\Core\Extensions;

use Modules\Core\Schemas\Extension\ExtensionSchema;

/**
 * The canonical five-field state the admin extensions API returns, for
 * one slug. Purely a function of current database rows and Composer
 * facts — never of boot-recorded diagnostics (those are per-process
 * boot artifacts and can be stale under persistent Octane workers).
 *
 * skip_reason precedence — the most immediate operational blocker first:
 *   missing_row    — no registry row (the boot guard treats this as
 *                    disabled, fail-closed)
 *   not_installed  — row exists but the Composer package is gone
 *   disabled       — registry row present but disabled
 *   null           — no derivable blocker. NOT a guarantee the
 *                    extension will boot: compatibility is decided by
 *                    the provider's own manifest() at boot time and the
 *                    guard stays fail-closed.
 */
class ExtensionStateBuilder
{
    public function __construct(
        protected ExtensionManager $manager,
        protected ComposerPackages $packages,
    ) {}

    /** @return array<string, mixed> */
    public function build(string $slug): array
    {
        $row = $this->manager->row($slug);
        $package = $this->packages->package($slug);

        $skipReason = match (true) {
            $row === null => 'missing_row',
            $package === null => 'not_installed',
            ! (bool) $row->{ExtensionSchema::ENABLED} => 'disabled',
            default => null,
        };

        return [
            ExtensionSchema::SLUG => $slug,
            ExtensionSchema::ENABLED => (bool) $row?->{ExtensionSchema::ENABLED},
            ExtensionSchema::RES_INSTALLED => $package !== null,
            ExtensionSchema::RES_VERSION => $this->packages->version($slug),
            ExtensionSchema::RES_SKIP_REASON => $skipReason,
        ];
    }
}
