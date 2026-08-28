<?php

namespace Modules\Inventory\Actions\Admin\InventoryStock;

class ListInventoryStockAction extends AbstractInventoryStockAction
{
    public function handle()
    {
        return $this->model
            ->paginate(15)
            ->withQueryString();
    }
}
