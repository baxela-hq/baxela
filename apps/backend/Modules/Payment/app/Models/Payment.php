<?php

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Payment\Database\Factories\PaymentFactory;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;

class Payment extends Model
{
    use HasFactory;

    protected $table = PaymentSchema::TABLE;

    protected $fillable = [
        PaymentSchema::ORDER_ID,
        PaymentSchema::TRANSACTION_ID,
        PaymentSchema::METHOD,
        PaymentSchema::AMOUNT,
        PaymentSchema::STATUS,
    ];

    protected function casts(): array
    {
        return [
            PaymentSchema::METHOD => PaymentMethodEnum::class,
            PaymentSchema::STATUS => PaymentStatusEnum::class,
        ];
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }
}
