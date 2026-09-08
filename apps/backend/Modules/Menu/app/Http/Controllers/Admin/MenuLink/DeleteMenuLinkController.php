<?php

namespace Modules\Menu\Http\Controllers\Admin\MenuLink;

use App\Http\Controllers\Controller;
use Modules\Menu\Actions\Admin\MenuLink\DeleteMenuLinkAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteMenuLinkController extends Controller
{
    public function __construct(protected DeleteMenuLinkAction $action) {}

    public function __invoke(string $id, string $linkId): Response
    {
        $this->action->handle($id, $linkId);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
