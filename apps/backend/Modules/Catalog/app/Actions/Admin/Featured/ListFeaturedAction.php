<?php

namespace Modules\Catalog\Actions\Admin\Featured;

use Illuminate\Support\Collection;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\FeaturedItem;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;

class ListFeaturedAction
{
    /**
     * All sections ordered by their featured position.
     *
     * @return array{product: Collection<Product>,
     *               category: Collection<Category>,
     *               brand: Collection<Brand>}
     */
    public function handle(): array
    {
        return [
            FeaturedItemSchema::RES_PRODUCT => Product::query()
                ->whereHas('featuredItem')
                ->with([
                    ProductSchema::RES_TRANSLATIONS,
                    ProductSchema::RES_VARIANTS,
                    ProductSchema::RES_IMAGES,
                ])
                ->orderBy($this->positionSubquery(
                    FeaturedItemSchema::TYPE_PRODUCT,
                    ProductSchema::TABLE.'.'.ProductSchema::ID
                ))
                ->get(),

            FeaturedItemSchema::RES_CATEGORY => Category::query()
                ->whereHas('featuredItem')
                ->with(CategorySchema::RES_TRANSLATIONS)
                ->orderBy($this->positionSubquery(
                    FeaturedItemSchema::TYPE_CATEGORY,
                    CategorySchema::TABLE.'.'.CategorySchema::ID
                ))
                ->get(),

            FeaturedItemSchema::RES_BRAND => Brand::query()
                ->whereHas('featuredItem')
                ->with(BrandSchema::RES_TRANSLATIONS)
                ->orderBy($this->positionSubquery(
                    FeaturedItemSchema::TYPE_BRAND,
                    BrandSchema::TABLE.'.'.BrandSchema::ID
                ))
                ->get(),
        ];
    }

    private function positionSubquery(string $type, string $qualifiedColumn)
    {
        return FeaturedItem::query()
            ->select(FeaturedItemSchema::POSITION)
            ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, $type)
            ->whereColumn(FeaturedItemSchema::FEATUREDABLE_ID, $qualifiedColumn);
    }
}
