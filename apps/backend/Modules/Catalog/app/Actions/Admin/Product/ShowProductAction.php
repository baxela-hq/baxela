<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Schemas\Product\ProductAttributeValueSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;

class ShowProductAction extends AbstractProductAction
{
    public function handle(string $id): Model
    {
        return $this->model
            ->with([
                ProductSchema::RES_CATEGORIES,
                ProductSchema::RES_IMAGES,
                ProductSchema::RES_TRANSLATIONS,
                ProductSchema::RES_SEO,
                ProductSchema::RES_VARIANTS.'.'.ProductSchema::RES_OPTION_VALUES,
                ProductSchema::RES_ATTRIBUTE_VALUES.'.'.ProductAttributeValueSchema::RES_ATTRIBUTE,
            ])
            ->findOrFail($id);
    }
}
