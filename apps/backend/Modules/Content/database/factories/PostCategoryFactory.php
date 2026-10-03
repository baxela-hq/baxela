<?php

namespace Modules\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Content\Models\PostCategory;
use Modules\Content\Models\PostCategoryTranslation;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;

class PostCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PostCategory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            PostCategorySchema::PARENT_ID => null,
            PostCategorySchema::POSITION => $this->faker->optional()->numberBetween(1, 100),
        ];
    }

    public function withTranslations(int $count = 1): self
    {
        return $this->has(
            PostCategoryTranslation::factory()->count($count),
            'translations'
        );
    }
}
