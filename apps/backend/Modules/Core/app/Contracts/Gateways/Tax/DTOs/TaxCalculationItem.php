<?php

namespace Modules\Core\Contracts\Gateways\Tax\DTOs;

class TaxCalculationItem
{
    public function __construct(
        public readonly int $variant_id,
        /** Post-promotion line total in minor units (price_snapshot × qty). */
        public readonly int $amount_minor,
        /** Null resolves to the default tax class at calculation time. */
        public readonly ?int $tax_class_id = null,
    ) {}
}
