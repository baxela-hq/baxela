<?php

namespace Modules\Content\Http\Controllers\Admin\PostComment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Content\Actions\Admin\PostComment\ListPostCommentAction;
use Modules\Content\Transformers\Admin\PostComment\PostCommentResource;

class ListPostCommentController extends Controller
{
    public function __construct(protected ListPostCommentAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return PostCommentResource::collection($this->action->handle());
    }
}
