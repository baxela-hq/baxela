<?php

namespace Modules\Order\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Order\Database\Factories\OrderAddressFactory;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;

/**
 * @property int $id
 * @property int $order_id
 * @property string $type
 * @property string $full_name
 * @property string $phone
 * @property string $address_line
 * @property string $city
 * @property string|null $postal_code
 * @property string|null $country_code
 * @property string|null $region_code
 * @property string|null $vat_id
 * @property CarbonInterface $created_at
 * @property-read Order $order
 */
class OrderAddress extends Model
{
    /** @use HasFactory<OrderAddressFactory> */
    use HasFactory;

    use TenantScopedTrait;

    protected $table = OrderAddressSchema::TABLE;

    public $timestamps = false;

    protected $fillable = [
        OrderAddressSchema::ORDER_ID,
        OrderAddressSchema::TYPE,
        OrderAddressSchema::FULL_NAME,
        OrderAddressSchema::PHONE,
        OrderAddressSchema::ADDRESS_LINE,
        OrderAddressSchema::CITY,
        OrderAddressSchema::POSTAL_CODE,
        OrderAddressSchema::COUNTRY_CODE,
        OrderAddressSchema::REGION_CODE,
        OrderAddressSchema::VAT_ID,
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function newFactory(): OrderAddressFactory
    {
        return OrderAddressFactory::new();
    }
}
