<?php

namespace Modules\Content\Http\Controllers\Admin\Post;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Content\Actions\Admin\Post\CreatePostAction;
use Modules\Content\Http\Requests\Admin\Post\PostRequest;
use Modules\Content\Transformers\Admin\Post\PostResource;

class CreatePostController extends Controller
{
    public function __construct(protected CreatePostAction $action) {}

    public function __invoke(PostRequest $request): JsonResponse
    {
        return PostResource::make($this->action->handle($request->validated()))
            ->response()
            ->setStatusCode(201);
    }
}
