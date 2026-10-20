<?php

namespace Modules\Order\Actions\User\OrderReturn;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Core\Utils\Auth;
use Modules\Core\Utils\Pagination;
use Modules\Order\Models\OrderReturn;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;

class ListOrderReturnsAction
{
    public function handle(): LengthAwarePaginator
    {
        return OrderReturn::query()
            ->where(OrderReturnSchema::USER_ID, Auth::id())
            ->with(OrderReturnSchema::RES_ORDER)
            ->orderBy(OrderReturnSchema::ID, 'desc')
            ->paginate(Pagination::perPage());
    }
}
