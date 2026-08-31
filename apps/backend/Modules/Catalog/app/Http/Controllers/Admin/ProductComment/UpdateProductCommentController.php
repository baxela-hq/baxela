<?php

namespace Modules\Catalog\Http\Controllers\Admin\ProductComment;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\ProductComment\UpdateProductCommentAction;
use Modules\Catalog\Exceptions\ProductComment\InvalidParentException;
use Modules\Catalog\Exceptions\ProductComment\UpdateFailedException;
use Modules\Catalog\Http\Requests\Admin\ProductComment\ProductCommentRequest;
use Modules\Catalog\Transformers\Admin\ProductComment\ProductCommentResource;

class UpdateProductCommentController extends Controller
{
    public function __construct(protected UpdateProductCommentAction $action) {}

    /**
     * @throws InvalidParentException|UpdateFailedException
     */
    public function __invoke(string $id, ProductCommentRequest $request): ProductCommentResource
    {
        return ProductCommentResource::make($this->action->handle($id, $request->validated()));
    }
}
