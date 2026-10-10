<?php

namespace Modules\Catalog\Http\Controllers\Admin\Brand;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Admin\Brand\ListBrandAction;
use Modules\Catalog\Transformers\Admin\Brand\BrandResource;

class ListBrandController extends Controller
{
    public function __construct(protected ListBrandAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return BrandResource::collection($this->action->handle());
    }
}
