<?php

namespace Modules\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Content\Models\PostCategory;
use Modules\Content\Models\PostCategoryTranslation;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema;

class PostCategoryTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PostCategoryTranslation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            PostCategoryTranslationSchema::POST_CATEGORY_ID => PostCategory::query()->inRandomOrder()->value(PostCategorySchema::ID),
            PostCategoryTranslationSchema::LANGUAGE_ID => $this->faker->numberBetween(1, 2),
            PostCategoryTranslationSchema::TITLE => $this->faker->word(),
            PostCategoryTranslationSchema::SLUG => $this->faker->unique()->slug(),
            PostCategoryTranslationSchema::DESCRIPTION => $this->faker->sentence(),
        ];
    }
}
