<?php

namespace Modules\Order\Actions\Admin\OrderReturn;

use Modules\Order\Models\OrderReturn;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;

class ShowOrderReturnAction
{
    public function __construct(protected OrderReturn $model) {}

    public function handle(string $id): OrderReturn
    {
        return $this->model->query()
            ->with(OrderReturnSchema::RES_ORDER.'.'.OrderSchema::RES_ITEMS)
            ->findOrFail($id);
    }
}
