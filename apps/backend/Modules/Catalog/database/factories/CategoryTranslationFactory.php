<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema;

class CategoryTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            CategoryTranslationSchema::CATEGORY_ID => Category::query()->inRandomOrder()->first()->id ?? null,
            CategoryTranslationSchema::LANGUAGE_ID => $this->faker->numberBetween(1, 2),
            CategoryTranslationSchema::TITLE => $this->faker->word(),
            CategoryTranslationSchema::SLUG => $this->faker->slug(),
            CategoryTranslationSchema::DESCRIPTION => $this->faker->sentence(),
        ];
    }
}
