<?php

namespace Modules\Catalog\Http\Controllers\Admin\Category;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Category\UpdateCategoryAction;
use Modules\Catalog\Http\Requests\Admin\Category\CategoryRequest;
use Modules\Catalog\Transformers\Admin\Category\CategoryResource;

class UpdateCategoryController extends Controller
{
    public function __construct(protected UpdateCategoryAction $action) {}

    public function __invoke(string $id, CategoryRequest $request): CategoryResource
    {
        return new CategoryResource($this->action->handle($id, $request->validated()));
    }
}
