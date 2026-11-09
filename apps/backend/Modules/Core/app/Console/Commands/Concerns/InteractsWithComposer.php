<?php

namespace Modules\Core\Console\Commands\Concerns;

use Illuminate\Support\Facades\Process;
use Modules\Core\Extensions\Extension;
use Modules\Core\Schemas\Extension\ExtensionSchema;

/**
 * Shared machinery of the baxela:* commands: slug validation, streamed
 * composer sub-processes, and provider resolution. Composer always runs
 * as a sub-process (never in-process) so its output is verbatim and it
 * owns its own autoload state.
 */
trait InteractsWithComposer
{
    /**
     * Run a composer command in the app root, streaming its output
     * verbatim as it arrives.
     *
     * @param  list<string>  $arguments
     */
    protected function composer(array $arguments): bool
    {
        $result = Process::timeout(900)
            ->path(base_path())
            ->run(
                array_merge(['composer', ...$arguments, '--no-interaction']),
                fn (string $type, string $buffer): bool => $this->displayProcessBuffer($type, $buffer),
            );

        return $result->successful();
    }

    protected function validSlug(string $slug): bool
    {
        if (preg_match('~'.ExtensionSchema::SLUG_PATTERN.'~', $slug) === 1) {
            return true;
        }

        $this->error("Invalid package name [{$slug}]: expected vendor/baxela-slug, lowercase.");

        return false;
    }

    /**
     * The registered (or freshly registered) provider instance, when it
     * is a Baxela extension provider. Registering a provider in an
     * already-booted app runs its guarded boot() immediately — a
     * disabled or incompatible extension no-ops there by design.
     */
    protected function resolveExtensionProvider(string $providerClass): ?Extension
    {
        $provider = app()->getProvider($providerClass) ?? app()->register($providerClass);

        return $provider instanceof Extension ? $provider : null;
    }

    protected function displayProcessBuffer(string $type, string $buffer): bool
    {
        if ($this->output !== null) {
            $this->output->write($buffer);
        }

        return false;
    }
}
