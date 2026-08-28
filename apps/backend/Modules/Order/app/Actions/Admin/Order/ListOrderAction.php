<?php

namespace Modules\Order\Actions\Admin\Order;

use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListOrderAction
{
    public function handle(): AbstractPaginator|LengthAwarePaginator
    {
        return QueryBuilder::for(Order::class)
            ->allowedIncludes(OrderSchema::RES_ITEMS)
            ->allowedFilters(
                AllowedFilter::partial(OrderSchema::TOTAL_AMOUNT),
                AllowedFilter::exact(OrderSchema::STATUS),
                AllowedFilter::exact(OrderSchema::USER_ID),
            )
            ->allowedSorts(
                OrderSchema::ID,
                OrderSchema::TOTAL_AMOUNT,
            )
            ->orderBy(OrderSchema::ID, 'desc')
            ->paginate(10);
    }
}
