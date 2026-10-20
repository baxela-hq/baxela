<?php

namespace Modules\Core\Contracts\Gateways\Order\DTOs;

class OrderItemTax
{
    public function __construct(
        public readonly int $variant_id,
        /** Tax charged on the line, minor units. */
        public readonly int $tax_minor,
        /** Exact applied rate, storage-format decimal string (e.g. '8.5000'). */
        public readonly string $rate,
        /** The class actually applied (product's class or resolved default). */
        public readonly ?int $tax_class_id = null,
    ) {}
}
