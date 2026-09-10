<?php

namespace Modules\Menu\Actions\Public\Menu;

use Illuminate\Database\Eloquent\Model;
use Modules\Menu\Schemas\Menu\MenuSchema;

class ShowMenuAction extends AbstractMenuAction
{
    public function handle(string $location): Model
    {
        return $this->scope()
            ->where(MenuSchema::LOCATION, $location)
            ->firstOrFail();
    }
}
