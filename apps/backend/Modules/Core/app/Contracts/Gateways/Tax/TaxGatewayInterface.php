<?php

namespace Modules\Core\Contracts\Gateways\Tax;

use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationInput;
use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxResult;
use Modules\Core\Exceptions\Tax\TaxConfigurationException;

interface TaxGatewayInterface
{
    /**
     * Whether the tax class can be referenced (exists). Used by other
     * modules to validate product-form selections — parallels
     * CatalogGatewayInterface::variantExists().
     */
    public function taxClassExists(int $taxClassId): bool;

    /**
     * Tax-exclusive calculation for a set of order lines. Semantics:
     *
     *   - A line with a null tax class resolves to the default class.
     *   - A missing or inactive rate for (country, class) is a VALID
     *     zero-tax configuration — the line taxes at zero.
     *   - A line whose tax class id does not resolve is a data-integrity
     *     problem and throws TaxConfigurationException (never a silent
     *     zero). The order-level coupon discount is allocated across the
     *     lines (largest remainder) BEFORE rates apply, so seller-funded
     *     coupons reduce the taxable consideration.
     *   - Shipping cost is taxed only through the class flagged
     *     is_shipping; no rate for the country means untaxed shipping.
     *
     * All amounts are integer minor units; the result is computed by the
     * same routine the checkout pipeline persists, so preview and order
     * figures agree by construction.
     *
     * @throws TaxConfigurationException
     */
    public function calculateForItems(TaxCalculationInput $input): TaxResult;
}
