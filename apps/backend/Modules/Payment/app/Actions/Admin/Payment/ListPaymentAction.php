<?php

namespace Modules\Payment\Actions\Admin\Payment;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Payment\Models\Payment;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListPaymentAction
{
    public function handle(): LengthAwarePaginator
    {
        return QueryBuilder::for(Payment::class)
            ->allowedFilters(
                AllowedFilter::exact(PaymentSchema::STATUS),
                AllowedFilter::exact(PaymentSchema::METHOD),
                AllowedFilter::exact(PaymentSchema::ORDER_ID),
                AllowedFilter::partial(PaymentSchema::TRANSACTION_ID),
            )
            ->allowedSorts(
                PaymentSchema::ID,
                PaymentSchema::AMOUNT,
                PaymentSchema::CREATED_AT,
            )
            ->defaultSort('-'.PaymentSchema::ID)
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
