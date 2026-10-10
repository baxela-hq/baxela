<?php

namespace Modules\Catalog\Http\Controllers\Admin\Brand;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Brand\UpdateBrandAction;
use Modules\Catalog\Http\Requests\Admin\Brand\BrandRequest;
use Modules\Catalog\Transformers\Admin\Brand\BrandResource;

class UpdateBrandController extends Controller
{
    public function __construct(protected UpdateBrandAction $action) {}

    public function __invoke(string $id, BrandRequest $request): BrandResource
    {
        return new BrandResource($this->action->handle($id, $request->validated()));
    }
}
