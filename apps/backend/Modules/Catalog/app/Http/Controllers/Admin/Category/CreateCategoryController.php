<?php

namespace Modules\Catalog\Http\Controllers\Admin\Category;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Catalog\Actions\Admin\Category\CreateCategoryAction;
use Modules\Catalog\Http\Requests\Admin\Category\CategoryRequest;
use Modules\Catalog\Transformers\Admin\Category\CategoryResource;

class CreateCategoryController extends Controller
{
    public function __construct(protected CreateCategoryAction $action) {}

    public function __invoke(CategoryRequest $request): JsonResponse
    {
        return CategoryResource::make($this->action->handle($request->validated()))
            ->response()
            ->setStatusCode(201);
    }
}
