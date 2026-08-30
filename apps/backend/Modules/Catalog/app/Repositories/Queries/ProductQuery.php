<?php

namespace Modules\Catalog\Repositories\Queries;

use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Contracts\Queries\QueryInterface;
use Modules\Core\Contracts\Queries\QueryTrait;
use Modules\Core\Repositories\Filter\TranslationTitleFilter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductQuery implements QueryInterface
{
    use QueryTrait;

    public function build(): QueryBuilder
    {
        return QueryBuilder::for(Product::class)
            ->allowedIncludes(
                ProductSchema::RES_VARIANTS,
                ProductSchema::RES_OPTIONS,
                ProductSchema::RES_CATEGORIES
            )
            ->allowedFilters(
                AllowedFilter::custom(PTSchema::TITLE, new TranslationTitleFilter),
                AllowedFilter::exact(ProductSchema::ID),
                AllowedFilter::exact(ProductSchema::STATUS),
                AllowedFilter::exact(ProductSchema::IS_PUBLISHED),
                AllowedFilter::exact(ProductSchema::TYPE),
                AllowedFilter::exact(ProductSchema::RES_CATEGORIES.'.'.CategorySchema::ID),
                AllowedFilter::exact(ProductSchema::RES_VARIANTS.'.'.VariantSchema::SKU),
                AllowedFilter::partial(ProductSchema::RES_VARIANTS.'.'.VariantSchema::PRICE),
            )
            ->allowedSorts(
                ProductSchema::ID,
            )
            ->with(
                ProductSchema::RES_TRANSLATIONS,
                ProductSchema::RES_VARIANTS,
            )
            ->orderBy(ProductSchema::ID, 'desc');
    }
}
