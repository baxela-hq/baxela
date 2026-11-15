<?php

namespace Modules\Payment\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Payment\Database\Factories\PaymentFactory;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;

/**
 * @property int $id
 * @property int $order_id
 * @property string|null $transaction_id
 * @property string $method
 * @property string $amount
 * @property int|null $currency_id
 * @property PaymentStatusEnum $status
 * @property string|null $gateway_transaction_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $table = PaymentSchema::TABLE;

    protected $fillable = [
        PaymentSchema::ORDER_ID,
        PaymentSchema::TRANSACTION_ID,
        PaymentSchema::GATEWAY_TRANSACTION_ID,
        PaymentSchema::METHOD,
        PaymentSchema::AMOUNT,
        PaymentSchema::CURRENCY_ID,
        PaymentSchema::STATUS,
    ];

    protected function casts(): array
    {
        return [
            PaymentSchema::STATUS => PaymentStatusEnum::class,
        ];
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }
}
