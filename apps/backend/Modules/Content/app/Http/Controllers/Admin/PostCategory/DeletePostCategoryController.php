<?php

namespace Modules\Content\Http\Controllers\Admin\PostCategory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Admin\PostCategory\DeletePostCategoryAction;
use Symfony\Component\HttpFoundation\Response;

class DeletePostCategoryController extends Controller
{
    public function __construct(protected DeletePostCategoryAction $action) {}

    public function __invoke(string $id, Request $request): \Illuminate\Http\Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
