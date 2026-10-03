<?php

namespace Modules\Content\Http\Controllers\Admin\PostCategory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Admin\PostCategory\ShowPostCategoryAction;
use Modules\Content\Transformers\Admin\PostCategory\PostCategoryResource;

class ShowPostCategoryController extends Controller
{
    public function __construct(protected ShowPostCategoryAction $action) {}

    public function __invoke(string $id, Request $request): PostCategoryResource
    {
        return new PostCategoryResource($this->action->handle($id));
    }
}
