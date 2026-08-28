<?php

namespace Modules\Inventory\Actions\Admin\InventoryStock;

use Illuminate\Database\Eloquent\Model;

class ShowInventoryStockAction extends AbstractInventoryStockAction
{
    public function handle(string $id): Model
    {
        return $this->model->findOrFail($id);
    }
}
