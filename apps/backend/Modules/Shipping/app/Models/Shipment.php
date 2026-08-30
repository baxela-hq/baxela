<?php

namespace Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Shipping\Database\Factories\ShipmentFactory;
use Modules\Shipping\Schemas\Shipment\ShipmentSchema;
use Modules\Shipping\Schemas\Shipment\ShipmentStatusEnum;

/**
 * No order() relation on purpose: orders live in another module and are
 * only reachable through the order gateway.
 */
class Shipment extends Model
{
    use HasFactory;

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
