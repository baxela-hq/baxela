<?php

namespace Modules\Catalog\Gateways;

use Illuminate\Support\Collection;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Image\ImageSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema as OVTSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Catalog\Support\ResolvesPublicLanguage;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Contracts\Gateways\Catalog\DTOs\ProductSummary;
use Modules\Core\Contracts\Gateways\Catalog\DTOs\VariantSummary;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;

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

    public function getVariantSummaries(array $variantIds): Collection
    {
        if ($variantIds === []) {
            return collect();
        }

        $languageId = $this->resolvePublicLanguageId();

        return Variant::query()
            ->whereIn(VariantSchema::ID, $variantIds)
            ->with([
                VariantSchema::RES_OPTION_VALUES.'.'.OptionValueSchema::RES_TRANSLATIONS,
                VariantSchema::RES_PRODUCT.'.'.ProductSchema::RES_TRANSLATIONS,
                VariantSchema::RES_PRODUCT.'.'.ProductSchema::RES_IMAGES,
            ])
            ->get()
            ->keyBy(VariantSchema::ID)
            ->map(fn (Variant $variant) => $this->toVariantSummary($variant, $languageId));
    }

    public function variantExists(int $variantId): bool
    {
        return Variant::query()->whereKey($variantId)->exists();
    }

    public function getProductSlugForVariant(int $variantId): ?string
    {
        $productId = Variant::query()
            ->whereKey($variantId)
            ->value(VariantSchema::PRODUCT_ID);

        if (is_null($productId)) {
            return null;
        }

        $defaultLanguageId = app(CoreGatewayInterface::class)->getDefaultLanguage()?->id;

        return ProductTranslation::query()
            ->where(PTSchema::PRODUCT_ID, $productId)
            ->orderByRaw(PTSchema::LANGUAGE_ID.' = ? desc', [$defaultLanguageId])
            ->whereNotNull(PTSchema::SLUG)
            ->value(PTSchema::SLUG);
    }

    public function variantQuantities(): Collection
    {
        return Variant::query()->pluck(VariantSchema::QUANTITY, VariantSchema::ID);
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

    private function toVariantSummary(Variant $variant, ?int $languageId): VariantSummary
    {
        $product = $variant->{VariantSchema::RES_PRODUCT};
        $productTranslation = $product?->{ProductSchema::RES_TRANSLATIONS}
            ->firstWhere(PTSchema::LANGUAGE_ID, $languageId)
            ?? $product?->{ProductSchema::RES_TRANSLATIONS}->first();

        $labels = $variant->{VariantSchema::RES_OPTION_VALUES}
            ->map(function ($optionValue) use ($languageId): ?string {
                $translation = $optionValue->{OptionValueSchema::RES_TRANSLATIONS}
                    ->firstWhere(OVTSchema::LANGUAGE_ID, $languageId)
                    ?? $optionValue->{OptionValueSchema::RES_TRANSLATIONS}->first();

                return $translation?->{OVTSchema::TITLE};
            })
            ->filter()
            ->values();

        return new VariantSummary(
            id: $variant->{VariantSchema::ID},
            product_id: $variant->{VariantSchema::PRODUCT_ID},
            price: $variant->{VariantSchema::PRICE},
            compare_price: $variant->{VariantSchema::COMPARE_PRICE},
            product_title: $productTranslation?->{PTSchema::TITLE},
            product_slug: $productTranslation?->{PTSchema::SLUG},
            image_url: $product?->{ProductSchema::RES_IMAGES}->first()?->{ImageSchema::URL},
            variant_label: $labels->isNotEmpty() ? $labels->join(' / ') : null,
        );
    }
}
