<?php

namespace Modules\Menu\Http\Controllers\Admin\Menu;

use App\Http\Controllers\Controller;
use Modules\Menu\Actions\Admin\Menu\DeleteMenuAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteMenuController extends Controller
{
    public function __construct(protected DeleteMenuAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
