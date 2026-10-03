<?php

namespace Modules\Content\Http\Controllers\Public\PostComment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Public\PostComment\ListPostCommentsAction;
use Modules\Content\Transformers\Public\PostComment\PostCommentResource;

class ListPostCommentsController extends Controller
{
    public function __construct(protected ListPostCommentsAction $action) {}

    public function __invoke(string $id, Request $request)
    {
        return PostCommentResource::collection($this->action->handle($id));
    }
}
