<?php

namespace Modules\Catalog\Http\Controllers\Public\Category;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Public\Category\ListCategoryAction;
use Modules\Catalog\Transformers\Public\Category\CategoryResource;

class ListCategoryController extends Controller
{
    public function __construct(protected ListCategoryAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->action->handle());
    }
}
