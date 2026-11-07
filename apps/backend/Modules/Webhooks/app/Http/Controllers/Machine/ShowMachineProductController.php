<?php

namespace Modules\Webhooks\Http\Controllers\Machine;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Transformers\Admin\Product\ProductResource;

class ShowMachineProductController extends MachineController
{
    public function __invoke(string $id): JsonResource
    {
        return new ProductResource(Product::query()->findOrFail($id));
    }
}
