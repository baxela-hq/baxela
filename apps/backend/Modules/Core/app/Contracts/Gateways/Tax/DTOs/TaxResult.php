<?php

namespace Modules\Core\Contracts\Gateways\Tax\DTOs;

class TaxResult
{
    /**
     * @param  array<int, TaxedLine>  $lines  keyed by variant id
     */
    public function __construct(
        /** Σ line taxes + shipping tax, minor units. */
        public readonly int $total_minor = 0,
        public readonly array $lines = [],
        public readonly int $shipping_tax_minor = 0,
        public readonly ?string $shipping_rate = null,
        public readonly ?int $shipping_tax_class_id = null,
    ) {}
}
