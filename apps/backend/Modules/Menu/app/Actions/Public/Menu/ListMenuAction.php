<?php

namespace Modules\Menu\Actions\Public\Menu;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ListMenuAction extends AbstractMenuAction
{
    public function handle(Request $request): Collection
    {
        return $this->scope()->get();
    }
}
