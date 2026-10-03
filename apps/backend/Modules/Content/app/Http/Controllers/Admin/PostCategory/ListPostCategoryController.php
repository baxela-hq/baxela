<?php

namespace Modules\Content\Http\Controllers\Admin\PostCategory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Content\Actions\Admin\PostCategory\ListPostCategoryAction;
use Modules\Content\Transformers\Admin\PostCategory\PostCategoryResource;

class ListPostCategoryController extends Controller
{
    public function __construct(protected ListPostCategoryAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return PostCategoryResource::collection($this->action->handle());
    }
}
