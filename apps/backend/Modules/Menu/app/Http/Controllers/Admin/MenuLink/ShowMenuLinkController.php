<?php

namespace Modules\Menu\Http\Controllers\Admin\MenuLink;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Menu\Actions\Admin\MenuLink\ShowMenuLinkAction;
use Modules\Menu\Transformers\Admin\MenuLink\MenuLinkResource;

class ShowMenuLinkController extends Controller
{
    public function __construct(protected ShowMenuLinkAction $action) {}

    public function __invoke(string $id, string $linkId, Request $request): MenuLinkResource
    {
        return MenuLinkResource::make($this->action->handle($id, $linkId));
    }
}
