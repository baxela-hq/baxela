<?php

namespace Modules\Catalog\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Catalog\Actions\Admin\Product\CreateProductAction;
use Modules\Catalog\Exceptions\Product\CreationFailedException;
use Modules\Catalog\Http\Requests\Admin\Product\ProductRequest;
use Modules\Catalog\Transformers\Admin\Product\ProductResource;
use Symfony\Component\HttpFoundation\Response;

class CreateProductController extends Controller
{
    public function __construct(protected CreateProductAction $action) {}

    /**
     * @throws CreationFailedException
     * @throws \Throwable
     */
    public function __invoke(ProductRequest $request): JsonResponse
    {
        return (new ProductResource($this->action->handle($request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
