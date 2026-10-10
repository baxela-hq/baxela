<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\BrandTranslation;
use Modules\Catalog\Schemas\Brand\BrandTranslationSchema;

class BrandTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = BrandTranslation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            BrandTranslationSchema::LANGUAGE_ID => $this->faker->numberBetween(1, 2),
            BrandTranslationSchema::TITLE => $this->faker->word(),
            BrandTranslationSchema::SLUG => $this->faker->slug(),
            BrandTranslationSchema::DESCRIPTION => $this->faker->sentence(),
        ];
    }
}
