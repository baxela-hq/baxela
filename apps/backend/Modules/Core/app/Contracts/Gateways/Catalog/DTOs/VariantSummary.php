<?php

namespace Modules\Core\Contracts\Gateways\Catalog\DTOs;

/**
 * The compact representation of a product variant: its pricing plus the
 * language-resolved display fields a cart/order line needs (product title
 * and slug, and the variant's option values joined as "S / Black").
 * Catalog owns how these are built; consumers never see Catalog's models
 * or storage.
 */
final readonly class VariantSummary
{
    public function __construct(
        public int $id,
        public int $product_id,
        public ?string $price,
        public ?string $compare_price,
        public ?string $product_title,
        public ?string $product_slug,
        public ?string $variant_label,
    ) {}
}
