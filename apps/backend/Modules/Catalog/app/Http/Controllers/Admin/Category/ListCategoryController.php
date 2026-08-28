<?php

namespace Modules\Catalog\Http\Controllers\Admin\Category;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Admin\Category\ListCategoryAction;
use Modules\Catalog\Transformers\Admin\Category\CategoryResource;

class ListCategoryController extends Controller
{
    public function __construct(protected ListCategoryAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->action->handle());
    }
}
