<?php

namespace Modules\Catalog\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Product\UpdateProductAction;
use Modules\Catalog\Http\Requests\Admin\Product\ProductRequest;
use Modules\Catalog\Transformers\Admin\Product\ProductResource;

class UpdateProductController extends Controller
{
    public function __construct(protected UpdateProductAction $action) {}

    public function __invoke(string $id, ProductRequest $request): ProductResource
    {
        return ProductResource::make($this->action->handle($id, $request->validated()));
    }
}
