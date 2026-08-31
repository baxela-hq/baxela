<?php

namespace Modules\Catalog\Http\Controllers\User\ProductComment;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Catalog\Actions\User\ProductComment\CreateProductCommentAction;
use Modules\Catalog\Exceptions\ProductComment\CreationFailedException;
use Modules\Catalog\Exceptions\ProductComment\InvalidParentException;
use Modules\Catalog\Http\Requests\User\ProductComment\ProductCommentRequest;
use Modules\Catalog\Transformers\User\ProductComment\ProductCommentResource;
use Symfony\Component\HttpFoundation\Response;

class CreateProductCommentController extends Controller
{
    public function __construct(protected CreateProductCommentAction $action) {}

    /**
     * @throws InvalidParentException|CreationFailedException
     */
    public function __invoke(string $id, ProductCommentRequest $request): JsonResponse
    {
        return (new ProductCommentResource($this->action->handle($id, $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
