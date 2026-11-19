<?php

namespace Modules\Catalog\Search;

use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Support\Money;

/**
 * Builds the product-level search document — ONE document per product,
 * the approved index model (review v13 F1). Flattens exactly what the
 * storefront filters on:
 *
 *  - title_<locale> for every REAL translation whose language is active
 *    (strict locale parity with the SQL whereHas: no fallback titles,
 *    missing translation = no match in that locale);
 *  - price_min / price_max: cheapest/priciest variant in MINOR units —
 *    the cross-variant envelope that reproduces the two independent
 *    whereHas price filters and both price-sort directions exactly;
 *  - option_value_ids: union over ALL variants (per-option OR happens
 *    at filter time, mirroring the one-whereHas-per-option semantics);
 *  - featured_position for the relevance tiebreak.
 *
 * Status/published flags are deliberately NOT in the document: only
 * eligible products (IN_STOCK + published) get documents at all —
 * absence is the mandatory constraint, hydration the second boundary.
 */
final class ProductDocumentBuilder
{
    public function __construct(private readonly CoreGatewayInterface $coreGateway) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Product $product): array
    {
        $product->loadMissing([
            'translations',
            'categories',
            'variants.optionValues',
            'featuredItem',
        ]);

        $doc = [
            'product_id' => $product->id,
            // Sortable epoch seconds (Meili sorts numerically).
            'created_at' => $product->created_at->getTimestamp(),
            'category_ids' => $product->categories->modelKeys(),
            'brand_id' => $product->brand_id,
            'rating_avg' => $product->rating_avg !== null ? (float) $product->rating_avg : null,
            'featured_position' => $product->featuredItem?->position !== null
                ? (int) $product->featuredItem->{FeaturedItemSchema::POSITION}
                : null,
        ];

        $codes = $this->activeLanguageCodes();

        foreach ($product->translations as $translation) {
            $code = $codes[$translation->{PTSchema::LANGUAGE_ID}] ?? null;

            if ($code !== null) {
                $doc['title_'.$code] = $translation->{PTSchema::TITLE};
            }
        }

        $optionValueIds = [];
        $prices = [];

        foreach ($product->variants as $variant) {
            $prices[] = Money::fromDecimal((string) $variant->{VariantSchema::PRICE}, 2);

            foreach ($variant->optionValues as $optionValue) {
                $optionValueIds[] = $optionValue->id;
            }
        }

        $doc['option_value_ids'] = array_values(array_unique($optionValueIds));
        $doc['price_min'] = $prices === [] ? null : min($prices);
        $doc['price_max'] = $prices === [] ? null : max($prices);

        return $doc;
    }

    /**
     * Active language id → code, resolved per call (stateless by design;
     * language activation changes are picked up by the next document).
     *
     * @return array<int, string>
     */
    private function activeLanguageCodes(): array
    {
        return $this->coreGateway
            ->getActiveLanguages()
            ->mapWithKeys(fn ($language) => [$language->id => $language->code])
            ->all();
    }
}
