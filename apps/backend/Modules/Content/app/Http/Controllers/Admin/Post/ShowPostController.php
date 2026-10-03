<?php

namespace Modules\Content\Http\Controllers\Admin\Post;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Admin\Post\ShowPostAction;
use Modules\Content\Transformers\Admin\Post\PostResource;

class ShowPostController extends Controller
{
    public function __construct(protected ShowPostAction $action) {}

    public function __invoke(string $id, Request $request): PostResource
    {
        return new PostResource($this->action->handle($id));
    }
}
