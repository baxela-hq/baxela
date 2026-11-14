<?php

namespace Modules\Inventory\Actions\Admin\InventoryStock;

use Modules\Inventory\Http\Requests\Admin\InventoryStock\InventoryStockRequest;
use Modules\Inventory\Models\InventoryStock;

class CreateInventoryStockAction extends AbstractInventoryStockAction
{
    public function handle(InventoryStockRequest $request): ?InventoryStock
    {
        $record = $this->model->create($request->validated());
        $record = $record->fresh([self::WITH_VARIANT_PRODUCT]);

        return $record;
    }
}
