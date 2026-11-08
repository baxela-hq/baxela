<?php

namespace Modules\Core\Contracts\Gateways\Setting;

/**
 * Resolves the store-level pricing mode (gap-C1b D1): whether displayed
 * prices contain tax. Bound by the Setting module; cached — every
 * settings write flushes the key. A missing/garbage value resolves to
 * the historical exclusive mode (false).
 */
interface PricingModeResolverInterface
{
    public function pricesIncludeTax(): bool;
}
