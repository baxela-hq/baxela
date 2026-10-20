<?php

namespace Modules\Payment\Transformers\Admin\PaymentRefund;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;

class PaymentRefundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            PaymentRefundSchema::ID => $this->{PaymentRefundSchema::ID},
            PaymentRefundSchema::UUID => $this->{PaymentRefundSchema::UUID},
            PaymentRefundSchema::PAYMENT_ID => $this->{PaymentRefundSchema::PAYMENT_ID},
            PaymentRefundSchema::ORDER_ID => $this->{PaymentRefundSchema::ORDER_ID},
            PaymentRefundSchema::AMOUNT => $this->{PaymentRefundSchema::AMOUNT},
            PaymentRefundSchema::REASON => $this->{PaymentRefundSchema::REASON},
            PaymentRefundSchema::STATUS => $this->{PaymentRefundSchema::STATUS},
            PaymentRefundSchema::TRANSACTION_ID => $this->{PaymentRefundSchema::TRANSACTION_ID},
            PaymentRefundSchema::REFUNDED_BY => $this->{PaymentRefundSchema::REFUNDED_BY},
            PaymentRefundSchema::CREATED_AT => $this->{PaymentRefundSchema::CREATED_AT},
            PaymentRefundSchema::UPDATED_AT => $this->{PaymentRefundSchema::UPDATED_AT},
        ];
    }
}
