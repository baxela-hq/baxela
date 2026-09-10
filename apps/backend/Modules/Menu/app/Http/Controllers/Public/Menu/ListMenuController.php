<?php

namespace Modules\Menu\Http\Controllers\Public\Menu;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Menu\Actions\Public\Menu\ListMenuAction;
use Modules\Menu\Transformers\Public\Menu\MenuResource;

class ListMenuController extends Controller
{
    public function __construct(protected ListMenuAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return MenuResource::collection($this->action->handle(request()));
    }
}
