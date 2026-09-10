<?php

namespace Modules\Catalog\Gateways;

use Illuminate\Support\Collection;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Image\ImageSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Catalog\Support\ResolvesPublicLanguage;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Contracts\Gateways\Catalog\DTOs\ProductSummary;

class CatalogGateway implements CatalogGatewayInterface
{
    use ResolvesPublicLanguage;

    public function getProductSummaries(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        $languageId = $this->resolvePublicLanguageId();

        return Product::query()
            ->where(ProductSchema::IS_PUBLISHED, true)
            ->whereIn(ProductSchema::ID, $productIds)
            ->with([
                ProductSchema::RES_TRANSLATIONS,
                ProductSchema::RES_IMAGES,
                // Only the pricing variant is ever needed for a summary.
                ProductSchema::RES_VARIANTS => fn ($query) => $query->where(VariantSchema::IS_DEFAULT, true),
            ])
            ->get()
            ->keyBy(ProductSchema::ID)
            ->map(fn (Product $product) => $this->toSummary($product, $languageId));
    }

    public function productExists(int $productId): bool
    {
        return Product::query()->whereKey($productId)->exists();
    }

    /**
     * Request-language translation with a first-translation fallback —
     * the same resolution the public product list applies.
     */
    private function toSummary(Product $product, ?int $languageId): ProductSummary
    {
        $translation = $product->{ProductSchema::RES_TRANSLATIONS}
            ->firstWhere(PTSchema::LANGUAGE_ID, $languageId)
            ?? $product->{ProductSchema::RES_TRANSLATIONS}->first();

        $variant = $product->{ProductSchema::RES_VARIANTS}->first();

        return new ProductSummary(
            id: $product->{ProductSchema::ID},
            title: $translation?->{PTSchema::TITLE},
            slug: $translation?->{PTSchema::SLUG},
            price: $variant?->{VariantSchema::PRICE},
            compare_price: $variant?->{VariantSchema::COMPARE_PRICE},
            image_url: $product->{ProductSchema::RES_IMAGES}->first()?->{ImageSchema::URL},
        );
    }
}
