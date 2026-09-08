<?php

namespace Modules\Menu\Http\Controllers\Admin\Menu;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Menu\Actions\Admin\Menu\ListMenuAction;
use Modules\Menu\Transformers\Admin\Menu\MenuResource;

class ListMenuController extends Controller
{
    public function __construct(protected ListMenuAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return MenuResource::collection($this->action->handle());
    }
}
