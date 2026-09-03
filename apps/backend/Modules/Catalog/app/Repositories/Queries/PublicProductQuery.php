<?php

namespace Modules\Catalog\Repositories\Queries;

use Illuminate\Database\Eloquent\Builder;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Contracts\Queries\QueryInterface;
use Modules\Core\Contracts\Queries\QueryTrait;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Storefront-facing product listing. Publication scopes are forced and
 * cannot be overridden from the request:
 *   GET /api/v1/catalog/public/products
 *     ?filter[title]=sneak
 *     &filter[categories.id]=3
 *     &filter[option_value_id]=5|6   (repeats allowed)
 *     &filter[price_min]=10&filter[price_max]=50
 *     &sort=-price|price|created_at|id
 *     &per_page=1..50
 */
class PublicProductQuery implements QueryInterface
{
    use QueryTrait;

    public function __construct(private readonly ?int $languageId = null) {}

    public function build(): QueryBuilder
    {
        return QueryBuilder::for(Product::class)
            ->allowedFilters(
                AllowedFilter::callback(PTSchema::TITLE, function (Builder $query, $value): void {
                    $query->whereHas(ProductSchema::RES_TRANSLATIONS, function (Builder $translation) use ($value): void {
                        $translation->where(PTSchema::TITLE, 'LIKE', "%{$value}%");

                        if (! is_null($this->languageId)) {
                            $translation->where(PTSchema::LANGUAGE_ID, $this->languageId);
                        }
                    });
                }),
                AllowedFilter::exact(ProductSchema::RES_CATEGORIES.'.'.CategorySchema::ID),
                AllowedFilter::callback('option_value_id', function (Builder $query, $value): void {
                    $query->whereHas(ProductSchema::RES_VARIANTS, function (Builder $variant) use ($value): void {
                        $variant->whereHas(
                            VariantSchema::RES_OPTION_VALUES,
                            fn (Builder $optionValue) => $optionValue->whereIn(OptionValueSchema::ID, (array) $value)
                        );
                    });
                }),
                AllowedFilter::callback('price_min', function (Builder $query, $value): void {
                    $query->whereHas(
                        ProductSchema::RES_VARIANTS,
                        fn (Builder $variant) => $variant->where(VariantSchema::PRICE, '>=', $value)
                    );
                }),
                AllowedFilter::callback('price_max', function (Builder $query, $value): void {
                    $query->whereHas(
                        ProductSchema::RES_VARIANTS,
                        fn (Builder $variant) => $variant->where(VariantSchema::PRICE, '<=', $value)
                    );
                }),
            )
            ->allowedSorts(
                ProductSchema::ID,
                ProductSchema::CREATED_AT,
                AllowedSort::callback('price', function (Builder $query, bool $descending): void {
                    $direction = $descending ? 'desc' : 'asc';

                    // Sort by the product's cheapest (priciest, descending)
                    // variant so multi-variant products order sensibly.
                    $query->orderBy(
                        Variant::query()
                            ->select(VariantSchema::PRICE)
                            ->whereColumn(VariantSchema::PRODUCT_ID, ProductSchema::TABLE.'.'.ProductSchema::ID)
                            ->orderBy(VariantSchema::PRICE, $direction)
                            ->limit(1),
                        $direction
                    );
                }),
            )
            ->defaultSort('-'.ProductSchema::ID)
            ->where(ProductSchema::STATUS, ProductStatusEnum::IN_STOCK)
            ->where(ProductSchema::IS_PUBLISHED, true)
            ->with([
                ProductSchema::RES_TRANSLATIONS,
                ProductSchema::RES_VARIANTS.'.'.VariantSchema::RES_OPTION_VALUES.'.'.OptionValueSchema::RES_TRANSLATIONS,
                ProductSchema::RES_IMAGES,
                ProductSchema::RES_CATEGORIES.'.'.CategorySchema::RES_TRANSLATIONS,
            ]);
    }
}
