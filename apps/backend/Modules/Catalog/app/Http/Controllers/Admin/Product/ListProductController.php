<?php

namespace Modules\Catalog\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Admin\Product\ListProductAction;
use Modules\Catalog\Transformers\Admin\Product\ProductResource;

class ListProductController extends Controller
{
    public function __construct(protected ListProductAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection($this->action->handle());
    }
}
