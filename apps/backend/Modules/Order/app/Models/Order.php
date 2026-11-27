<?php

namespace Modules\Order\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Utils\OrderCodeGenerator;

/**
 * @property int $id
 * @property int $user_id
 * @property string $order_code
 * @property OrderStatusEnum $status
 * @property OrderPaymentStatusEnum $payment_status
 * @property CarbonInterface|null $paid_at
 * @property string $total_amount
 * @property int|null $currency_id
 * @property int|null $shipping_method_id
 * @property string|null $shipping_method_name
 * @property string $shipping_cost
 * @property int|null $coupon_id
 * @property string|null $coupon_code
 * @property string $discount_amount
 * @property string $tax_amount
 * @property string $shipping_tax_amount
 * @property string $shipping_tax_rate
 * @property int|null $shipping_tax_class_id
 * @property string $refunded_amount
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 *
 * @mixin Builder<Order>
 *
 * @property bool $prices_contain_tax
 * @property string|null $buyer_vat_id
 * @property string|null $display_currency_code
 * @property string|null $display_fx_rate
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use TenantScopedTrait;

    protected $table = OrderSchema::TABLE;

    protected $fillable = [
        OrderSchema::USER_ID,
        OrderSchema::ORDER_CODE,
        OrderSchema::STATUS,
        OrderSchema::PAYMENT_STATUS,
        OrderSchema::PAID_AT,
        OrderSchema::TOTAL_AMOUNT,
        OrderSchema::CURRENCY_ID,
        OrderSchema::DISPLAY_CURRENCY_CODE,
        OrderSchema::DISPLAY_FX_RATE,
        OrderSchema::SHIPPING_METHOD_ID,
        OrderSchema::SHIPPING_METHOD_NAME,
        OrderSchema::SHIPPING_COST,
        OrderSchema::SHIPPING_TAX_AMOUNT,
        OrderSchema::SHIPPING_TAX_RATE,
        OrderSchema::SHIPPING_TAX_CLASS_ID,
        OrderSchema::COUPON_ID,
        OrderSchema::COUPON_CODE,
        OrderSchema::DISCOUNT_AMOUNT,
        OrderSchema::TAX_AMOUNT,
        OrderSchema::PRICES_CONTAIN_TAX,
        OrderSchema::BUYER_VAT_ID,
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

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    /** @return HasMany<OrderInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(OrderInvoice::class);
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}
