<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;

class InventoryStockFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = InventoryStock::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            InventoryStockSchema::VARIANT_ID => $this->faker->unique()->numberBetween(1, 40),
            InventoryStockSchema::QUANTITY => $this->faker->randomDigit(),
        ];
    }
}
