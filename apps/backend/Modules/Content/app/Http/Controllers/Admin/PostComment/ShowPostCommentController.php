<?php

namespace Modules\Content\Http\Controllers\Admin\PostComment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Admin\PostComment\ShowPostCommentAction;
use Modules\Content\Transformers\Admin\PostComment\PostCommentResource;

class ShowPostCommentController extends Controller
{
    public function __construct(protected ShowPostCommentAction $action) {}

    public function __invoke(string $id, Request $request): PostCommentResource
    {
        return new PostCommentResource($this->action->handle($id));
    }
}
