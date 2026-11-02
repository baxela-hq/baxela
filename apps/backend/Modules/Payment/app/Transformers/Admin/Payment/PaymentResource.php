<?php

namespace Modules\Payment\Transformers\Admin\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Schemas\Payment\PaymentSchema;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $manager = app(PaymentDriverManager::class);

        return [
            PaymentSchema::ID => $this->resource->{PaymentSchema::ID},
            PaymentSchema::ORDER_ID => $this->resource->{PaymentSchema::ORDER_ID},
            PaymentSchema::TRANSACTION_ID => $this->resource->{PaymentSchema::TRANSACTION_ID},
            PaymentSchema::METHOD => $this->resource->{PaymentSchema::METHOD},
            PaymentSchema::AMOUNT => $this->resource->{PaymentSchema::AMOUNT},
            PaymentSchema::CURRENCY_ID => $this->resource->{PaymentSchema::CURRENCY_ID},
            PaymentSchema::STATUS => $this->resource->{PaymentSchema::STATUS},
            PaymentSchema::IS_REFUNDABLE => $manager->supportsRefund(
                $this->resource->{PaymentSchema::METHOD}
            ),
            PaymentSchema::CREATED_AT => $this->resource->{PaymentSchema::CREATED_AT},
            PaymentSchema::UPDATED_AT => $this->resource->{PaymentSchema::UPDATED_AT},
        ];
    }
}
