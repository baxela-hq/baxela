<?php

namespace Modules\Core\Actions\Admin\Extension;

use Modules\Core\Exceptions\Extension\ExtensionsException;
use Modules\Core\Extensions\ComposerPackages;
use Modules\Core\Extensions\ExtensionManager;
use Modules\Core\Extensions\ExtensionStateBuilder;

/**
 * Enables or disables an extension over HTTP (marketplace P5.1) — the
 * same machinery the baxela:enable / baxela:disable commands run, with
 * the CLI's guardrails preserved:
 *
 * - enable requires BOTH the registry row and the Composer package; a
 *   missing row is a domain error (core.extensions.not_installed) and
 *   the action never implicitly creates one — the boot guard treats
 *   row-less extensions as disabled, fail-closed;
 * - disable requires only the row: a leftover row (package removed from
 *   vendor) stays disableable, exactly like the CLI;
 * - the toggle is write-through; flush() afterwards keeps this
 *   process's view honest for anything reading later in the request.
 *
 * What this changes is PERSISTED state. Under Octane, service providers
 * boot once per worker: an already-running worker keeps an extension's
 * registered bindings (e.g. merged payment drivers) until octane:reload
 * or a container restart. This action deliberately does not reload —
 * the admin UI surfaces the nuance (roadmap P5.4).
 */
class ToggleExtensionAction
{
    public function __construct(
        protected ExtensionManager $manager,
        protected ComposerPackages $packages,
        protected ExtensionStateBuilder $builder,
    ) {}

    /**
     * @return array<string, mixed> the post-mutation state — current rows
     *                              and Composer facts, never boot-recorded diagnostics
     */
    public function handle(string $slug, bool $enabled): array
    {
        if ($enabled) {
            if ($this->manager->row($slug) === null || $this->packages->package($slug) === null) {
                throw ExtensionsException::notInstalled($slug);
            }

            $this->manager->enable($slug);
        } else {
            if ($this->manager->row($slug) === null) {
                throw ExtensionsException::notInstalled($slug);
            }

            $this->manager->disable($slug);
        }

        ExtensionManager::flush();

        // The toggle changes which extensions effectively run, so the
        // update-check cache must not survive it (P5.3).
        CheckExtensionUpdatesAction::flush();

        return $this->builder->build($slug);
    }
}
