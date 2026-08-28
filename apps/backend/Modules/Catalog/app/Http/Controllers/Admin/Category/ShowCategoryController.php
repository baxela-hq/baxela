<?php

namespace Modules\Catalog\Http\Controllers\Admin\Category;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Actions\Admin\Category\ShowCategoryAction;
use Modules\Catalog\Transformers\Admin\Category\CategoryResource;

class ShowCategoryController extends Controller
{
    public function __construct(protected ShowCategoryAction $action) {}

    public function __invoke(string $id, Request $request): CategoryResource
    {
        return CategoryResource::make($this->action->handle($id));
    }
}
