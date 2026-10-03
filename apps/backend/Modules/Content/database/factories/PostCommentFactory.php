<?php

namespace Modules\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostComment;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\PostComment\PostCommentSchema;
use Modules\Content\Schemas\PostComment\PostCommentStatusEnum;

class PostCommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PostComment::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            PostCommentSchema::POST_ID => Post::query()->inRandomOrder()->value(PostSchema::ID),
            PostCommentSchema::USER_ID => $this->faker->randomDigitNotZero(),
            PostCommentSchema::PARENT_ID => null,
            PostCommentSchema::BODY => $this->faker->paragraph(),
            PostCommentSchema::STATUS => $this->faker->randomElement(PostCommentStatusEnum::cases()),
        ];
    }
}
