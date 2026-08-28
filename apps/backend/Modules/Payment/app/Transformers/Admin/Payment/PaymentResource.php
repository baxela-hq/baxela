<?php

namespace Modules\Payment\Transformers\Admin\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payment\Schemas\Payment\PaymentSchema;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            PaymentSchema::ORDER_ID => $this->resource->{PaymentSchema::ORDER_ID},
            PaymentSchema::TRANSACTION_ID => $this->resource->{PaymentSchema::TRANSACTION_ID},
            PaymentSchema::METHOD => $this->resource->{PaymentSchema::METHOD},
            PaymentSchema::AMOUNT => $this->resource->{PaymentSchema::AMOUNT},
            PaymentSchema::STATUS => $this->resource->{PaymentSchema::STATUS},
        ];
    }
}
