<?php

namespace Modules\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Content\Models\Page;
use Modules\Content\Models\PageTranslation;
use Modules\Content\Schemas\Page\PageTranslationSchema;

class PageTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PageTranslation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            PageTranslationSchema::PAGE_ID => Page::query()->inRandomOrder()->first()->id ?? null,
            PageTranslationSchema::LANGUAGE_ID => $this->faker->numberBetween(1, 2),
            PageTranslationSchema::TITLE => $this->faker->word(),
            PageTranslationSchema::SLUG => $this->faker->slug(),
            PageTranslationSchema::CONTENT => $this->faker->text(),
            PageTranslationSchema::DESCRIPTION => $this->faker->sentence(),
        ];
    }
}
