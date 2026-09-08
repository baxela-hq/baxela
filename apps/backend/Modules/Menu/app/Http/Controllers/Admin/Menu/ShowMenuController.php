<?php

namespace Modules\Menu\Http\Controllers\Admin\Menu;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Menu\Actions\Admin\Menu\ShowMenuAction;
use Modules\Menu\Transformers\Admin\Menu\MenuResource;

class ShowMenuController extends Controller
{
    public function __construct(protected ShowMenuAction $action) {}

    public function __invoke(string $id, Request $request): MenuResource
    {
        return MenuResource::make($this->action->handle($id));
    }
}
