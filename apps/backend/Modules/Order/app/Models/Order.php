<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Utils\OrderCodeGenerator;

/**
 * @mixin Builder
 */
class Order extends Model
{
    use HasFactory;

    protected $table = OrderSchema::TABLE;

    protected $fillable = [
        OrderSchema::USER_ID,
        OrderSchema::ORDER_CODE,
        OrderSchema::STATUS,
        OrderSchema::PAYMENT_STATUS,
        OrderSchema::PAID_AT,
        OrderSchema::TOTAL_AMOUNT,
        OrderSchema::SHIPPING_METHOD_ID,
        OrderSchema::SHIPPING_METHOD_NAME,
        OrderSchema::SHIPPING_COST,
        OrderSchema::EXPIRES_AT,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Order $model) {
            $model->{OrderSchema::ORDER_CODE} ??= app(OrderCodeGenerator::class)->generate();
        });
    }

    protected function casts(): array
    {
        return [
            OrderSchema::STATUS => OrderStatusEnum::class,
            OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::class,
            OrderSchema::PAID_AT => 'datetime',
            OrderSchema::EXPIRES_AT => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}
