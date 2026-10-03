<?php

namespace Modules\Content\Http\Controllers\Admin\PostComment;

use App\Http\Controllers\Controller;
use Modules\Content\Actions\Admin\PostComment\UpdatePostCommentAction;
use Modules\Content\Http\Requests\Admin\PostComment\PostCommentRequest;
use Modules\Content\Transformers\Admin\PostComment\PostCommentResource;

class UpdatePostCommentController extends Controller
{
    public function __construct(protected UpdatePostCommentAction $action) {}

    public function __invoke(string $id, PostCommentRequest $request): PostCommentResource
    {
        return new PostCommentResource($this->action->handle($id, $request->validated()));
    }
}
