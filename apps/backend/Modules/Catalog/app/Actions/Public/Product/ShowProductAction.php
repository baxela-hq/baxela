<?php

namespace Modules\Catalog\Actions\Public\Product;

use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\Product\ProductAttributeValueSchema as PAVSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Catalog\Support\AppliesProductPromotions;

class ShowProductAction extends AbstractProductAction
{
    public function __construct(Product $model, protected AppliesProductPromotions $appliesProductPromotions)
    {
        parent::__construct($model);
    }
    /**
     * Resolves the product by numeric id or by translation slug — the
     * storefront links products by slug. Numeric ids keep working for API
     * consumers.
     */
    public function handle(string $idOrSlug): Model
    {
        $query = $this->model
            ->where(ProductSchema::STATUS, ProductStatusEnum::IN_STOCK)
            ->where(ProductSchema::IS_PUBLISHED, true)
            ->with([
                ProductSchema::RES_TRANSLATIONS,
                ProductSchema::RES_VARIANTS.'.'.VariantSchema::RES_OPTION_VALUES.'.'.OptionValueSchema::RES_TRANSLATIONS,
                ProductSchema::RES_VARIANTS.'.'.VariantSchema::RES_IMAGES,
                ProductSchema::RES_IMAGES,
                ProductSchema::RES_CATEGORIES.'.'.CategorySchema::RES_TRANSLATIONS,
                ProductSchema::RES_ATTRIBUTE_VALUES.'.'.PAVSchema::RES_ATTRIBUTE.'.'.AttributeSchema::RES_TRANSLATIONS,
                ProductSchema::RES_ATTRIBUTE_VALUES.'.'.PAVSchema::RES_ATTRIBUTE_VALUE.'.'.AttributeValueSchema::RES_TRANSLATIONS,
            ]);

        if (ctype_digit($idOrSlug)) {
            $product = $query->findOrFail($idOrSlug);
        } else {
            $product = $query
                ->whereHas(
                    ProductSchema::RES_TRANSLATIONS,
                    fn ($translation) => $translation->where(PTSchema::SLUG, $idOrSlug)
                )
                ->firstOrFail();
        }

        // Promoted in memory (categories are already eager-loaded above, so
        // the resolver's scope matching needs no extra query)
        $this->appliesProductPromotions->apply(collect([$product]));

        return $product;
    }
}
