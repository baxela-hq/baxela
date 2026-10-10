<?php

namespace Modules\Catalog\Http\Controllers\Admin\Brand;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Catalog\Actions\Admin\Brand\CreateBrandAction;
use Modules\Catalog\Http\Requests\Admin\Brand\BrandRequest;
use Modules\Catalog\Transformers\Admin\Brand\BrandResource;

class CreateBrandController extends Controller
{
    public function __construct(protected CreateBrandAction $action) {}

    public function __invoke(BrandRequest $request): JsonResponse
    {
        return BrandResource::make($this->action->handle($request->validated()))
            ->response()
            ->setStatusCode(201);
    }
}
