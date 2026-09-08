<?php

namespace Modules\Menu\Http\Controllers\Admin\MenuLink;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Menu\Actions\Admin\MenuLink\ListMenuLinkAction;
use Modules\Menu\Transformers\Admin\MenuLink\MenuLinkResource;

class ListMenuLinkController extends Controller
{
    public function __construct(protected ListMenuLinkAction $action) {}

    public function __invoke(string $id): AnonymousResourceCollection
    {
        return MenuLinkResource::collection($this->action->handle($id));
    }
}
