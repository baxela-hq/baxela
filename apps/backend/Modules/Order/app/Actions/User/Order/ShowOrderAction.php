<?php

namespace Modules\Order\Actions\User\Order;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Utils\Auth;
use Modules\Order\Schemas\Order\OrderSchema;

class ShowOrderAction extends AbstractOrderAction
{
    public function handle(string $id): Model
    {
        return $this->order
            ->with(OrderSchema::RES_ADDRESSES)
            ->where(OrderSchema::ID, $id)
            ->where(OrderSchema::USER_ID, Auth::id())
            ->firstOrFail();
    }
}
