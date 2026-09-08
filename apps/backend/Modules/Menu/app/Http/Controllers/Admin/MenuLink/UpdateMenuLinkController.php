<?php

namespace Modules\Menu\Http\Controllers\Admin\MenuLink;

use App\Http\Controllers\Controller;
use Modules\Menu\Actions\Admin\MenuLink\UpdateMenuLinkAction;
use Modules\Menu\Http\Requests\Admin\MenuLink\MenuLinkRequest;
use Modules\Menu\Transformers\Admin\MenuLink\MenuLinkResource;

class UpdateMenuLinkController extends Controller
{
    public function __construct(protected UpdateMenuLinkAction $action) {}

    public function __invoke(string $id, string $linkId, MenuLinkRequest $request): MenuLinkResource
    {
        return MenuLinkResource::make($this->action->handle($id, $linkId, $request->validated()));
    }
}
