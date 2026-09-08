<?php

namespace Modules\Menu\Http\Controllers\Admin\Menu;

use App\Http\Controllers\Controller;
use Modules\Menu\Actions\Admin\Menu\UpdateMenuAction;
use Modules\Menu\Http\Requests\Admin\Menu\MenuRequest;
use Modules\Menu\Transformers\Admin\Menu\MenuResource;

class UpdateMenuController extends Controller
{
    public function __construct(protected UpdateMenuAction $action) {}

    public function __invoke(string $id, MenuRequest $request): MenuResource
    {
        return MenuResource::make($this->action->handle($id, $request->validated()));
    }
}
