<?php

namespace Modules\Payment\Actions\Admin\PaymentRefund;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Utils\Pagination;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;

class ListPaymentRefundsAction
{
    public function handle(string $paymentId): LengthAwarePaginator
    {
        return PaymentRefund::query()
            ->where(PaymentRefundSchema::PAYMENT_ID, $paymentId)
            ->orderBy(PaymentRefundSchema::ID, 'desc')
            ->paginate(Pagination::perPage());
    }
}
