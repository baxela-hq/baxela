<?php

namespace Modules\Catalog\Http\Controllers\Public\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Public\Product\ListProductAction;
use Modules\Catalog\Transformers\Public\Product\ListProductResource;

class ListProductController extends Controller
{
    public function __construct(protected ListProductAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return ListProductResource::collection($this->action->handle($request));
    }
}
