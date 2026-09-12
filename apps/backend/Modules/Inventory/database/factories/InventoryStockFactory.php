<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Variant;
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

    /**
     * A ledger row for a given variant at a given quantity. Named
     * ofVariant() to avoid colliding with Laravel's magic relationship
     * states.
     */
    public function ofVariant(Variant $variant, int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            InventoryStockSchema::VARIANT_ID => $variant->id,
            InventoryStockSchema::QUANTITY => $quantity,
        ]);
    }
}
