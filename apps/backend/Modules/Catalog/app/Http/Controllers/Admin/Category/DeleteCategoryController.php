<?php

namespace Modules\Catalog\Http\Controllers\Admin\Category;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Category\DeleteCategoryAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteCategoryController extends Controller
{
    public function __construct(protected DeleteCategoryAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
