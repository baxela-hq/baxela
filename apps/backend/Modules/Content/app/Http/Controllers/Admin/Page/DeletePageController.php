<?php

namespace Modules\Content\Http\Controllers\Admin\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Admin\Page\DeletePageAction;
use Symfony\Component\HttpFoundation\Response;

class DeletePageController extends Controller
{
    public function __construct(protected DeletePageAction $action) {}

    public function __invoke(string $id, Request $request): \Illuminate\Http\Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
