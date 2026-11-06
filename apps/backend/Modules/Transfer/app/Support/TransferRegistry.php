<?php

namespace Modules\Transfer\Support;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Laravel\Module as LaravelModule;

/**
 * Discovers the modules that opted into the data-transfer standard.
 *
 * Registration is per-module: a participating module declares its
 * implementation class in its own module config under `{lower}.transfer.
 * class` (its config is merged into Laravel config by its provider), so
 * joining the standard never touches the Transfer module itself.
 */
final class TransferRegistry
{
    /**
     * All enabled modules' transfers (empty for modules that did not
     * opt in), keyed by module lower name, alphabetically for a stable
     * capabilities listing.
     *
     * @return array<string, ModuleTransferInterface>
     */
    public function transfers(): array
    {
        $transfers = [];
        foreach ($this->enabledModules() as $module) {
            $name = $module->getLowerName();
            $transfer = $this->resolve($name);
            if ($transfer !== null) {
                $transfers[$name] = $transfer;
            }
        }

        ksort($transfers);

        return $transfers;
    }

    /**
     * Lower names of every enabled module — supported or not.
     *
     * @return list<string>
     */
    public function enabledModuleNames(): array
    {
        return array_map(
            fn (LaravelModule $module): string => $module->getLowerName(),
            $this->enabledModules(),
        );
    }

    public function forModule(string $module): ?ModuleTransferInterface
    {
        return $this->resolve($module);
    }

    private function resolve(string $module): ?ModuleTransferInterface
    {
        $class = config($module.'.transfer.class');

        if (! is_string($class)
            || ! class_exists($class)
            || ! is_subclass_of($class, ModuleTransferInterface::class)
        ) {
            return null;
        }

        $transfer = app($class);

        return $transfer instanceof ModuleTransferInterface ? $transfer : null;
    }

    /**
     * @return list<LaravelModule>
     */
    private function enabledModules(): array
    {
        return array_values(Module::allEnabled());
    }
}
