<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Image;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Image\ImageCollectionEnum;
use Modules\Catalog\Schemas\Image\ImageSchema;

class ImageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Image::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        return [
            ImageSchema::PRODUCT_ID => $params[ImageSchema::PRODUCT_ID] ?? Product::query()->inRandomOrder()->first(),
            ImageSchema::VARIANT_ID => $params[ImageSchema::VARIANT_ID] ?? Variant::query()->inRandomOrder()->first(),
            ImageSchema::MEDIA_ID => $params[ImageSchema::MEDIA_ID] ?? $this->faker->randomDigitNotZero(),
            ImageSchema::COLLECTION => $params[ImageSchema::COLLECTION] ?? $this->faker->randomElement(ImageCollectionEnum::cases()),
            ImageSchema::URL => $params[ImageSchema::URL] ?? $this->faker->imageUrl,
            ImageSchema::POSITION => $params[ImageSchema::POSITION] ?? $this->faker->numberBetween(1, 30),
        ];
    }
}
