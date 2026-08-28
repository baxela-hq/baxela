<?php

namespace Modules\Catalog\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Product\ShowProductAction;
use Modules\Catalog\Transformers\Admin\Product\ProductResource;

class ShowProductController extends Controller
{
    public function __construct(protected ShowProductAction $action) {}

    public function __invoke(string $id): ProductResource
    {
        return ProductResource::make($this->action->handle($id));
    }
}
