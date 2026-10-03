<?php

namespace Modules\Content\Http\Controllers\Admin\Post;

use App\Http\Controllers\Controller;
use Modules\Content\Actions\Admin\Post\UpdatePostAction;
use Modules\Content\Http\Requests\Admin\Post\PostRequest;
use Modules\Content\Transformers\Admin\Post\PostResource;

class UpdatePostController extends Controller
{
    public function __construct(protected UpdatePostAction $action) {}

    public function __invoke(string $id, PostRequest $request): PostResource
    {
        return new PostResource($this->action->handle($id, $request->validated()));
    }
}
