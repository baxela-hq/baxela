<?php

namespace Modules\Core\Contracts\Gateways\Catalog\DTOs;

/**
 * The compact, card-level representation of a product: exactly what a
 * product card renders (image, localized title/slug, default-variant
 * pricing). Catalog owns how these are built; consumers never see
 * Catalog's models or storage.
 */
final readonly class ProductSummary
{
    public function __construct(
        public int $id,
        public ?string $title,
        public ?string $slug,
        public ?string $price,
        public ?string $compare_price,
        public ?string $image_url,
    ) {}
}
