<?php

namespace Modules\Menu\Http\Controllers\Admin\MenuLink;

use App\Http\Controllers\Controller;
use Modules\Menu\Actions\Admin\MenuLink\CreateMenuLinkAction;
use Modules\Menu\Http\Requests\Admin\MenuLink\MenuLinkRequest;
use Modules\Menu\Transformers\Admin\MenuLink\MenuLinkResource;

class CreateMenuLinkController extends Controller
{
    public function __construct(protected CreateMenuLinkAction $action) {}

    public function __invoke(string $id, MenuLinkRequest $request): MenuLinkResource
    {
        return MenuLinkResource::make($this->action->handle($id, $request->validated()));
    }
}
