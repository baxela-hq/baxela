<?php

namespace Modules\Order\Actions\Admin\Invoice;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Utils\Pagination;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListOrderInvoicesAction
{
    /**
     * @return LengthAwarePaginator<OrderInvoice>
     */
    public function handle(): LengthAwarePaginator
    {
        $id = OrderInvoiceSchema::TABLE.'.'.OrderInvoiceSchema::ID;

        return QueryBuilder::for(OrderInvoice::class)
            ->allowedFilters(
                AllowedFilter::exact(OrderInvoiceSchema::STATUS),
                AllowedFilter::exact(OrderInvoiceSchema::ORDER_ID),
                AllowedFilter::partial(OrderInvoiceSchema::NUMBER),
            )
            ->allowedSorts(
                OrderInvoiceSchema::ID,
                OrderInvoiceSchema::CREATED_AT,
            )
            ->select([
                $id,
                OrderInvoiceSchema::ORDER_ID,
                OrderInvoiceSchema::REVISION,
                OrderInvoiceSchema::NUMBER,
                OrderInvoiceSchema::STATUS,
                OrderInvoiceSchema::ISSUED_AT,
                OrderInvoiceSchema::VOIDED_AT,
                OrderInvoiceSchema::CURRENCY_CODE,
                OrderInvoiceSchema::SUBTOTAL_AMOUNT,
                OrderInvoiceSchema::DISCOUNT_AMOUNT,
                OrderInvoiceSchema::COUPON_CODE,
                OrderInvoiceSchema::SHIPPING_AMOUNT,
                OrderInvoiceSchema::SHIPPING_TAX_AMOUNT,
                OrderInvoiceSchema::TAX_AMOUNT,
                OrderInvoiceSchema::TOTAL_AMOUNT,
                OrderInvoiceSchema::PDF_STATUS,
                OrderInvoiceSchema::TABLE.'.'.OrderInvoiceSchema::CREATED_AT,
                OrderInvoiceSchema::TABLE.'.'.OrderInvoiceSchema::UPDATED_AT,
            ])
            ->orderBy($id, 'desc')
            ->paginate(Pagination::perPage());
    }
}
