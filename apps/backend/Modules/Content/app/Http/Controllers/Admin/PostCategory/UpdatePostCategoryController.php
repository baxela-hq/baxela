<?php

namespace Modules\Content\Http\Controllers\Admin\PostCategory;

use App\Http\Controllers\Controller;
use Modules\Content\Actions\Admin\PostCategory\UpdatePostCategoryAction;
use Modules\Content\Http\Requests\Admin\PostCategory\PostCategoryRequest;
use Modules\Content\Transformers\Admin\PostCategory\PostCategoryResource;

class UpdatePostCategoryController extends Controller
{
    public function __construct(protected UpdatePostCategoryAction $action) {}

    public function __invoke(string $id, PostCategoryRequest $request): PostCategoryResource
    {
        return new PostCategoryResource($this->action->handle($id, $request->validated()));
    }
}
