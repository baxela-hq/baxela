<?php

namespace Modules\Catalog\Http\Controllers\Admin\ProductComment;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Catalog\Actions\Admin\ProductComment\CreateProductCommentAction;
use Modules\Catalog\Exceptions\ProductComment\CreationFailedException;
use Modules\Catalog\Exceptions\ProductComment\InvalidParentException;
use Modules\Catalog\Http\Requests\Admin\ProductComment\ProductCommentRequest;
use Modules\Catalog\Transformers\Admin\ProductComment\ProductCommentResource;
use Symfony\Component\HttpFoundation\Response;

class CreateProductCommentController extends Controller
{
    public function __construct(protected CreateProductCommentAction $action) {}

    /**
     * @throws InvalidParentException|CreationFailedException
     */
    public function __invoke(ProductCommentRequest $request): JsonResponse
    {
        return (new ProductCommentResource($this->action->handle($request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
