<?php

namespace Modules\Order\Actions\Admin\OrderReturn;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Utils\Pagination;
use Modules\Order\Models\OrderReturn;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListOrderReturnAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = OrderReturnSchema::TABLE.'.'.OrderReturnSchema::ID;

        return QueryBuilder::for(OrderReturn::class)
            ->allowedFilters(
                AllowedFilter::exact(OrderReturnSchema::STATUS),
                AllowedFilter::exact(OrderReturnSchema::ORDER_ID),
                AllowedFilter::exact(OrderReturnSchema::USER_ID),
            )
            ->allowedSorts(
                OrderReturnSchema::ID,
                OrderReturnSchema::CREATED_AT,
            )
            ->select([
                $id,
                OrderReturnSchema::ORDER_ID,
                OrderReturnSchema::USER_ID,
                OrderReturnSchema::STATUS,
                OrderReturnSchema::REASON,
                OrderReturnSchema::NOTE,
                OrderReturnSchema::ADMIN_NOTE,
                OrderReturnSchema::TRACKING_NUMBER,
                OrderReturnSchema::STOCK_RESTORED_AT,
                OrderReturnSchema::TABLE.'.'.OrderReturnSchema::CREATED_AT,
                OrderReturnSchema::TABLE.'.'.OrderReturnSchema::UPDATED_AT,
            ])
            ->with(OrderReturnSchema::RES_ORDER)
            ->orderBy($id, 'desc')
            ->paginate(Pagination::perPage());
    }
}
