<?php

namespace Modules\Catalog\Http\Controllers\User\ProductComment;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\User\ProductComment\ReactToProductCommentAction;
use Modules\Catalog\Exceptions\ProductComment\ReactionFailedException;
use Modules\Catalog\Http\Requests\User\ProductComment\ReactToProductCommentRequest;
use Modules\Catalog\Transformers\User\ProductComment\ProductCommentReactionResource;

class ReactToProductCommentController extends Controller
{
    public function __construct(protected ReactToProductCommentAction $action) {}

    /**
     * @throws ReactionFailedException
     */
    public function __invoke(string $id, ReactToProductCommentRequest $request): ProductCommentReactionResource
    {
        return new ProductCommentReactionResource($this->action->handle($id, $request->validated()));
    }
}
