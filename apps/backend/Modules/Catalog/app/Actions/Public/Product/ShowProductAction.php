<?php

namespace Modules\Catalog\Actions\Public\Product;

use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;

class ShowProductAction extends AbstractProductAction
{
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
                ProductSchema::RES_IMAGES,
                ProductSchema::RES_CATEGORIES.'.'.CategorySchema::RES_TRANSLATIONS,
            ]);

        if (ctype_digit($idOrSlug)) {
            return $query->findOrFail($idOrSlug);
        }

        return $query
            ->whereHas(
                ProductSchema::RES_TRANSLATIONS,
                fn ($translation) => $translation->where(PTSchema::SLUG, $idOrSlug)
            )
            ->firstOrFail();
    }
}
