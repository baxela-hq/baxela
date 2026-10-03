<?php

namespace Modules\Content\Http\Controllers\Admin\PostCategory;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Content\Actions\Admin\PostCategory\CreatePostCategoryAction;
use Modules\Content\Http\Requests\Admin\PostCategory\PostCategoryRequest;
use Modules\Content\Transformers\Admin\PostCategory\PostCategoryResource;

class CreatePostCategoryController extends Controller
{
    public function __construct(protected CreatePostCategoryAction $action) {}

    public function __invoke(PostCategoryRequest $request): JsonResponse
    {
        return PostCategoryResource::make($this->action->handle($request->validated()))
            ->response()
            ->setStatusCode(201);
    }
}
