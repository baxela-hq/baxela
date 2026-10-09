<?php

namespace Modules\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostSeoTranslation;
use Modules\Content\Schemas\Post\PostSeoTranslationSchema;

class PostSeoTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PostSeoTranslation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            PostSeoTranslationSchema::POST_ID => Post::factory(),
            PostSeoTranslationSchema::LANGUAGE_ID => $this->faker->numberBetween(1, 2),
            PostSeoTranslationSchema::META_TITLE => $this->faker->sentence(4),
            PostSeoTranslationSchema::META_DESCRIPTION => $this->faker->sentence(10),
            PostSeoTranslationSchema::OPEN_GRAPH_TITLE => $this->faker->sentence(4),
            PostSeoTranslationSchema::OPEN_GRAPH_DESCRIPTION => $this->faker->sentence(10),
        ];
    }
}
