<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;

class ProductCommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ProductComment::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        return [
            ProductCommentSchema::PRODUCT_ID => $params[ProductCommentSchema::PRODUCT_ID]
                ?? Product::query()->inRandomOrder()->first(),
            ProductCommentSchema::USER_ID => $params[ProductCommentSchema::USER_ID]
                ?? $this->faker->randomDigitNotZero(),
            ProductCommentSchema::PARENT_ID => $params[ProductCommentSchema::PARENT_ID]
                ?? null,
            ProductCommentSchema::BODY => $params[ProductCommentSchema::BODY]
                ?? $this->faker->paragraph(),
            ProductCommentSchema::STATUS => $params[ProductCommentSchema::STATUS]
                ?? $this->faker->randomElement(ProductCommentStatusEnum::cases()),
        ];
    }
}
