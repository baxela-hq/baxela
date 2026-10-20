<?php

namespace Modules\Payment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;

class PaymentRefundFactory extends Factory
{
    protected $model = PaymentRefund::class;

    public function definition(array $params = []): array
    {
        return [
            PaymentRefundSchema::UUID => $params[PaymentRefundSchema::UUID]
                ?? (string) \Illuminate\Support\Str::uuid(),
            PaymentRefundSchema::PAYMENT_ID => $params[PaymentRefundSchema::PAYMENT_ID]
                ?? Payment::factory(),
            PaymentRefundSchema::ORDER_ID => $params[PaymentRefundSchema::ORDER_ID]
                ?? null,
            PaymentRefundSchema::AMOUNT => $params[PaymentRefundSchema::AMOUNT]
                ?? $this->faker->randomFloat(2, 1, 100),
            PaymentRefundSchema::REASON => $params[PaymentRefundSchema::REASON]
                ?? $this->faker->sentence(),
            PaymentRefundSchema::STATUS => $params[PaymentRefundSchema::STATUS]
                ?? PaymentRefundStatusEnum::SUCCESS,
            PaymentRefundSchema::TRANSACTION_ID => $params[PaymentRefundSchema::TRANSACTION_ID]
                ?? null,
            PaymentRefundSchema::REFUNDED_BY => $params[PaymentRefundSchema::REFUNDED_BY]
                ?? null,
        ];
    }
}
