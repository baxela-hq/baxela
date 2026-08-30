<?php

namespace Modules\Shipping\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Schemas\Shipment\ShipmentSchema;
use Modules\Shipping\Schemas\Shipment\ShipmentStatusEnum;

class ShipmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Shipment::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        return [
            ShipmentSchema::ORDER_ID => $params[ShipmentSchema::ORDER_ID] ?? $this->faker->unique()->numberBetween(1, 20),
            ShipmentSchema::CARRIER_NAME => $this->faker->company(),
            ShipmentSchema::TRACKING_NUMBER => $this->faker->bothify('TRK-##########'),
            ShipmentSchema::TRACKING_URL => $this->faker->url(),
            ShipmentSchema::STATUS => $this->faker->randomElement(ShipmentStatusEnum::cases()),
            ShipmentSchema::SHIPPED_AT => null,
            ShipmentSchema::DELIVERED_AT => null,
            ShipmentSchema::NOTES => $this->faker->sentence(),
        ];
    }
}
