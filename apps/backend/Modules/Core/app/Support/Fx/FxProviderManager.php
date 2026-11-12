<?php

namespace Modules\Core\Support\Fx;

use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Fx\FxProviderInterface;

/**
 * Resolves the configured FX provider driver (Payment-module style,
 * review F7): the config registry holds ONLY the implementation class
 * and endpoint — credentials are resolved by the settings resolver and
 * injected explicitly at construction. Unknown slugs are a
 * deployment misconfiguration, not a runtime condition.
 */
class FxProviderManager
{
    /**
     * @param  string  $name  registry slug, e.g. "openexchangerates"
     * @param  string  $apiKey  credential resolved by the settings resolver
     */
    public function for(string $name, string $apiKey): FxProviderInterface
    {
        $provider = config("core.fx.providers.{$name}");

        if (! is_array($provider) || ! isset($provider['class'])) {
            throw new InvalidArgumentException("FX provider [{$name}] is not registered.");
        }

        return app($provider['class'], [
            'apiKey' => $apiKey,
            'baseUrl' => (string) ($provider['base_url'] ?? ''),
        ]);
    }
}
