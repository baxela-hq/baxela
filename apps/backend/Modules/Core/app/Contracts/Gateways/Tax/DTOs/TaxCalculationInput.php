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
        /** Cart-wide coupon discount in minor units, allocated across lines before rating. */
        public readonly int $coupon_discount_minor = 0,
        public readonly int $shipping_cost_minor = 0,
    ) {}
}
