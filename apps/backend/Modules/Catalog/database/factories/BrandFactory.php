<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Schemas\Brand\BrandSchema;

class BrandFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Brand::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            BrandSchema::POSITION => $this->faker->numberBetween(1, 20),
        ];
    }
}
