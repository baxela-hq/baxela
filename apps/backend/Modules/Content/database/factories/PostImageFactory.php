<?php

namespace Modules\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostImage;
use Modules\Content\Schemas\Post\PostImageCollectionEnum;
use Modules\Content\Schemas\Post\PostImageSchema;

class PostImageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PostImage::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        return [
            PostImageSchema::POST_ID => $params[PostImageSchema::POST_ID] ?? Post::factory(),
            PostImageSchema::MEDIA_ID => $params[PostImageSchema::MEDIA_ID] ?? $this->faker->randomDigitNotZero(),
            PostImageSchema::COLLECTION => $params[PostImageSchema::COLLECTION] ?? $this->faker->randomElement(PostImageCollectionEnum::cases()),
            PostImageSchema::URL => $params[PostImageSchema::URL] ?? $this->faker->imageUrl,
            PostImageSchema::POSITION => $params[PostImageSchema::POSITION] ?? $this->faker->numberBetween(1, 30),
        ];
    }
}
