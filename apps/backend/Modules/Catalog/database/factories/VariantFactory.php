<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Variant\VariantSchema;

class VariantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Variant::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        $price = $this->faker->numberBetween(100, 1000);

        return [
            VariantSchema::PRODUCT_ID => $params[VariantSchema::PRODUCT_ID] ?? Product::inRandomOrder()?->first()?->id,
            VariantSchema::SKU => $this->faker->unique()->slug(),
            VariantSchema::BARCODE => $this->faker->unique()->randomNumber(),
            VariantSchema::PRICE => $price,
            VariantSchema::QUANTITY => $this->faker->numberBetween(1, 100),
            VariantSchema::COMPARE_PRICE => $price + 10,
            VariantSchema::COST_PRICE => $price - 30,
            VariantSchema::IS_DEFAULT => $this->faker->boolean(),
        ];
    }
}
