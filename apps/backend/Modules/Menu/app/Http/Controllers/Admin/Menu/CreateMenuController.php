<?php

namespace Modules\Menu\Http\Controllers\Admin\Menu;

use App\Http\Controllers\Controller;
use Modules\Menu\Actions\Admin\Menu\CreateMenuAction;
use Modules\Menu\Http\Requests\Admin\Menu\MenuRequest;
use Modules\Menu\Transformers\Admin\Menu\MenuResource;

class CreateMenuController extends Controller
{
    public function __construct(protected CreateMenuAction $action) {}

    public function __invoke(MenuRequest $request): MenuResource
    {
        return MenuResource::make($this->action->handle($request->validated()));
    }
}
