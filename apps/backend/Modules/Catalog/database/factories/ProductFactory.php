<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTypeEnum;

class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            ProductSchema::TYPE => $this->faker->randomElement(ProductTypeEnum::cases()),
            ProductSchema::STATUS => $this->faker->randomElement(ProductStatusEnum::cases()),
            ProductSchema::IS_PUBLISHED => $this->faker->boolean(),
        ];
    }
}
