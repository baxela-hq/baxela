<?php

namespace Modules\Core\Contracts\Gateways\Tax\DTOs;

class TaxCalculationInput
{
    /**
     * @param  array<int, TaxCalculationItem>  $items
     */
    public function __construct(
        public readonly array $items = [],
        public readonly string $country_code = '',
        /**
         * ISO 3166-2 region of the tax jurisdiction (shipping
         * destination), '' when the address carries none (gap-C1a D4).
         */
        public readonly string $region_code = '',
        /** Cart-wide coupon discount in minor units, allocated across lines before rating. */
        public readonly int $coupon_discount_minor = 0,
        public readonly int $shipping_cost_minor = 0,
        /**
         * Store-level pricing mode (gap-C1b): when true, line amounts and
         * shipping are GROSS and the tax is EXTRACTED (informational),
         * never added to a total.
         */
        public readonly bool $prices_include_tax = false,
    ) {}
}
