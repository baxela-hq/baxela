<?php

namespace Modules\Menu\Http\Controllers\Public\Menu;

use App\Http\Controllers\Controller;
use Modules\Menu\Actions\Public\Menu\ShowMenuAction;
use Modules\Menu\Transformers\Public\Menu\MenuResource;

class ShowMenuController extends Controller
{
    public function __construct(protected ShowMenuAction $action) {}

    public function __invoke(string $location): MenuResource
    {
        return MenuResource::make($this->action->handle($location));
    }
}
