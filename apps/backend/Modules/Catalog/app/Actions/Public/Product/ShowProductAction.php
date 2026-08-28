<?php

namespace Modules\Catalog\Actions\Public\Product;

use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Schemas\Product\ProductSchema;

class ShowProductAction extends AbstractProductAction
{
    public function handle(string $id): Model
    {
        return $this->model->with(ProductSchema::RES_VARIANTS, ProductSchema::RES_CATEGORIES)->findOrFail($id);
    }
}
