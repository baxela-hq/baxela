<?php

namespace Modules\Catalog\Http\Controllers\Public\Brand;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Actions\Public\Brand\ShowBrandAction;
use Modules\Catalog\Transformers\Public\Brand\BrandResource;

class ShowBrandController extends Controller
{
    public function __construct(protected ShowBrandAction $action) {}

    public function __invoke(string $id, Request $request): BrandResource
    {
        return BrandResource::make($this->action->handle($id));
    }
}
