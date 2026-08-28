<?php

namespace Modules\Order\Actions\Admin\Order;

use Modules\Order\Http\Requests\Admin\Order\OrderRequest;
use Modules\Order\Schemas\Order\OrderSchema;

class UpdateOrderAction extends AbstractOrderAction
{
    public function handle(string $id, OrderRequest $request)
    {
        $record = $this->model->findOrFail($id);
        $record->update($request->validated());

        return $record->load(OrderSchema::RES_ITEMS);
    }
}
