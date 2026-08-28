<?php

namespace Modules\Inventory\Actions\Admin\InventoryStock;

use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Http\Requests\Admin\InventoryStock\InventoryStockRequest;

class UpdateInventoryStockAction extends AbstractInventoryStockAction
{
    public function handle(string $id, InventoryStockRequest $request): Model
    {
        $record = $this->model->findOrFail($id);
        $record->update($request->validated());

        return $record;
    }
}
