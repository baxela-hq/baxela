<?php

namespace Modules\Inventory\Actions\Admin\InventoryStock;

class DeleteInventoryStockAction extends AbstractInventoryStockAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->findOrFail($id);

        return $record->delete();
    }
}
