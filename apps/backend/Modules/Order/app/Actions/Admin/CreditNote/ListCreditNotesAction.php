<?php

namespace Modules\Order\Actions\Admin\CreditNote;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Order\Models\OrderCreditNote;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListCreditNotesAction
{
    /**
     * @return LengthAwarePaginator<int, OrderCreditNote>
     */
    public function handle(): LengthAwarePaginator
    {
        return QueryBuilder::for(OrderCreditNote::class)
            ->allowedFilters(
                AllowedFilter::exact(OrderCreditNoteSchema::STATUS),
                AllowedFilter::exact(OrderCreditNoteSchema::INVOICE_ID),
                AllowedFilter::exact(OrderCreditNoteSchema::REFUND_UUID),
            )
            ->defaultSort('-'.OrderCreditNoteSchema::ISSUED_AT)
            ->paginate(50);
    }
}
