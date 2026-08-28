<?php

namespace Modules\Order\Actions\User\Order;

use Modules\Core\Utils\Auth;
use Modules\Order\Schemas\Order\OrderSchema;

class ListOrderAction extends AbstractOrderAction
{
    public function handle()
    {
        return $this->order
            ->where(OrderSchema::USER_ID, Auth::id())
            ->paginate(15)
            ->withQueryString();
    }
}
