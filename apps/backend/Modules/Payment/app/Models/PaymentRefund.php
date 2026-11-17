<?php

namespace Modules\Payment\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payment\Database\Factories\PaymentRefundFactory;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;

/**
 * @mixin Builder<PaymentRefund>
 */
/**
 * @property int $id
 * @property string $uuid
 * @property int $payment_id
 * @property int $order_id
 * @property string $amount
 * @property string $reason
 * @property PaymentRefundStatusEnum $status
 * @property string|null $transaction_id
 * @property int|null $refunded_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Payment $payment
 */
class PaymentRefund extends Model
{
    /** @use HasFactory<PaymentRefundFactory> */
    use HasFactory;

    protected $table = PaymentRefundSchema::TABLE;

    protected $fillable = [
        PaymentRefundSchema::UUID,
        PaymentRefundSchema::PAYMENT_ID,
        PaymentRefundSchema::ORDER_ID,
        PaymentRefundSchema::AMOUNT,
        PaymentRefundSchema::REASON,
        PaymentRefundSchema::STATUS,
        PaymentRefundSchema::TRANSACTION_ID,
        PaymentRefundSchema::REFUNDED_BY,
    ];

    protected function casts(): array
    {
        return [
            PaymentRefundSchema::UUID => 'string',
            PaymentRefundSchema::AMOUNT => 'decimal:2',
            PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::class,
        ];
    }

    protected static function newFactory(): PaymentRefundFactory
    {
        return PaymentRefundFactory::new();
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, PaymentRefundSchema::PAYMENT_ID);
    }
}
