<?php

namespace Modules\Webhooks\Http\Controllers\Machine;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Transformers\Admin\Product\ProductResource;

class ListMachineProductsController extends MachineController
{
    public function __invoke(): AnonymousResourceCollection
    {
        return ProductResource::collection(
            Product::query()
                ->orderByDesc(ProductSchema::ID)
                ->jsonPaginate(),
        );
    }
}
