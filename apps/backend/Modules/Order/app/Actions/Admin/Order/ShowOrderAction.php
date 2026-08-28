<?php

namespace Modules\Order\Actions\Admin\Order;

use Modules\Order\Schemas\Order\OrderSchema;

class ShowOrderAction extends AbstractOrderAction
{
    public function handle(string $id)
    {
        $record = $this->model->findOrFail($id);

        return $record->load(OrderSchema::RES_ITEMS, OrderSchema::RES_ADDRESSES);
    }
}
