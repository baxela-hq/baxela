<?php

namespace Modules\Menu\Actions\Admin\Menu;

use Illuminate\Database\Eloquent\Model;
use Modules\Menu\Schemas\Menu\MenuSchema;

class ShowMenuAction extends AbstractMenuAction
{
    public function handle(string $id): Model
    {
        return $this->model->query()->with(MenuSchema::RES_TRANSLATIONS)->findOrFail($id);
    }
}
