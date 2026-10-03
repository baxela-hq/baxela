<?php

namespace Modules\Content\Http\Controllers\Public\Post;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Content\Actions\Public\Post\ListPostsAction;
use Modules\Content\Transformers\Public\Post\PostResource;

class ListPostController extends Controller
{
    public function __construct(protected ListPostsAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return PostResource::collection($this->action->handle($request));
    }
}
