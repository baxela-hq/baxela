<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductSeoTranslation;
use Modules\Catalog\Schemas\Product\ProductSeoTranslationSchema;

class ProductSeoTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ProductSeoTranslation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            ProductSeoTranslationSchema::PRODUCT_ID => Product::query()->inRandomOrder()->first()->id,
            ProductSeoTranslationSchema::LANGUAGE_ID => $this->faker->numberBetween(1, 2),
            ProductSeoTranslationSchema::META_TITLE => $this->faker->sentence(4),
            ProductSeoTranslationSchema::META_DESCRIPTION => $this->faker->sentence(10),
            ProductSeoTranslationSchema::OPEN_GRAPH_TITLE => $this->faker->sentence(4),
            ProductSeoTranslationSchema::OPEN_GRAPH_DESCRIPTION => $this->faker->sentence(10),
        ];
    }
}
