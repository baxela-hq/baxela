<?php

namespace Modules\Catalog\Actions\Public\Product;

use Illuminate\Http\Request;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;

class ListProductAction extends AbstractProductAction
{
    public function handle(Request $request)
    {
        return $this->model
            ->where(ProductSchema::STATUS, ProductStatusEnum::IN_STOCK)
            ->paginate(15)
            ->withQueryString();
    }
}
