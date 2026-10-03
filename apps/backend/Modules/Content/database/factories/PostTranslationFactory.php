<?php

namespace Modules\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostTranslation;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostTranslationSchema;

class PostTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PostTranslation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            PostTranslationSchema::POST_ID => Post::query()->inRandomOrder()->value(PostSchema::ID),
            PostTranslationSchema::LANGUAGE_ID => $this->faker->numberBetween(1, 2),
            PostTranslationSchema::TITLE => $this->faker->sentence(3),
            PostTranslationSchema::SLUG => $this->faker->unique()->slug(),
            PostTranslationSchema::CONTENT => $this->faker->text(),
            PostTranslationSchema::DESCRIPTION => $this->faker->sentence(),
        ];
    }
}
