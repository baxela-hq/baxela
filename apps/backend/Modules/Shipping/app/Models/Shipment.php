<?php

namespace Modules\Shipping\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Shipping\Database\Factories\ShipmentFactory;
use Modules\Shipping\Schemas\Shipment\ShipmentSchema;
use Modules\Shipping\Schemas\Shipment\ShipmentStatusEnum;

/**
 * No order() relation on purpose: orders live in another module and are
 * only reachable through the order gateway.
 */
/**
 * @property int $id
 * @property int $order_id
 * @property string|null $carrier_name
 * @property string|null $tracking_number
 * @property string|null $tracking_url
 * @property ShipmentStatusEnum $status
 * @property CarbonInterface|null $shipped_at
 * @property CarbonInterface|null $delivered_at
 * @property string|null $notes
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    use TenantScopedTrait;

    protected $table = ShipmentSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        ShipmentSchema::ORDER_ID,
        ShipmentSchema::CARRIER_NAME,
        ShipmentSchema::TRACKING_NUMBER,
        ShipmentSchema::TRACKING_URL,
        ShipmentSchema::STATUS,
        ShipmentSchema::SHIPPED_AT,
        ShipmentSchema::DELIVERED_AT,
        ShipmentSchema::NOTES,
    ];

    protected function casts(): array
    {
        return [
            ShipmentSchema::STATUS => ShipmentStatusEnum::class,
            ShipmentSchema::SHIPPED_AT => 'datetime',
            ShipmentSchema::DELIVERED_AT => 'datetime',
        ];
    }

    protected static function newFactory(): ShipmentFactory
    {
        return ShipmentFactory::new();
    }
}
