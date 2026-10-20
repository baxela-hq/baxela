<?php

namespace Modules\Core\Contracts\Gateways\Tax\DTOs;

class TaxedLine
{
    public function __construct(
        /** Tax charged on this line, minor units. */
        public readonly int $tax_minor,
        /** Exact applied rate as the storage-format decimal string (e.g. '5.1250'). */
        public readonly string $rate,
        /** The class actually applied (the product's class, or the resolved default). */
        public readonly ?int $tax_class_id,
    ) {}
}
