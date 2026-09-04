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
            // The resource only serializes addresses when loaded; the
            // storefront order list renders them in its detail view.
            ->with(OrderSchema::RES_ADDRESSES)
            ->paginate(15)
            ->withQueryString();
    }
}
