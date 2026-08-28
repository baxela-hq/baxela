<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;

class ProductTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ProductTranslation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            ProductTranslationSchema::PRODUCT_ID => Product::query()->inRandomOrder()->first()->id,
            ProductTranslationSchema::LANGUAGE_ID => $this->faker->numberBetween(1, 2),
            ProductTranslationSchema::TITLE => $this->faker->word(),
            ProductTranslationSchema::SLUG => $this->faker->slug(),
            ProductTranslationSchema::CONTENT => $this->faker->sentence(10),
            ProductTranslationSchema::DESCRIPTION => $this->faker->sentence(5),
        ];
    }
}
