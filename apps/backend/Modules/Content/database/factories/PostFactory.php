<?php

namespace Modules\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostTranslation;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;

class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            PostSchema::STATUS => $this->faker->randomElement(PostStatusEnum::cases()),
            PostSchema::IS_FEATURED => false,
        ];
    }

    public function withTranslations(int $count = 1): self
    {
        return $this->has(
            PostTranslation::factory()->count($count),
            'translations'
        );
    }
}
