<?php

namespace Modules\Catalog\Actions\Public\Product;

use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Variant\VariantSchema;

class ShowProductAction extends AbstractProductAction
{
    public function handle(string $id): Model
    {
        return $this->model
            ->where(ProductSchema::STATUS, ProductStatusEnum::IN_STOCK)
            ->where(ProductSchema::IS_PUBLISHED, true)
            ->with([
                ProductSchema::RES_TRANSLATIONS,
                ProductSchema::RES_VARIANTS.'.'.VariantSchema::RES_OPTION_VALUES.'.'.OptionValueSchema::RES_TRANSLATIONS,
                ProductSchema::RES_IMAGES,
                ProductSchema::RES_CATEGORIES.'.'.CategorySchema::RES_TRANSLATIONS,
            ])
            ->findOrFail($id);
    }
}
