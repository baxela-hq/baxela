<?php

namespace Modules\Catalog\Http\Controllers\Admin\Brand;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Actions\Admin\Brand\ShowBrandAction;
use Modules\Catalog\Transformers\Admin\Brand\BrandResource;

class ShowBrandController extends Controller
{
    public function __construct(protected ShowBrandAction $action) {}

    public function __invoke(string $id, Request $request): BrandResource
    {
        return BrandResource::make($this->action->handle($id));
    }
}
