<?php

namespace Modules\Media\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Media\MediaDiskEnum;
use Modules\Media\Schemas\Media\MediaMimeTypeEnum;
use Modules\Media\Schemas\Media\MediaSchema;

class MediaFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Media::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            MediaSchema::USER_ID => $this->faker->randomDigitNotZero(),
            MediaSchema::DISK => $this->faker->randomElement(MediaDiskEnum::cases()),
            MediaSchema::PATH => $this->faker->filePath(),
            MediaSchema::NAME => $this->faker->words(2, true),
            MediaSchema::FILENAME => $this->faker->slug(1).'.'.$this->faker->fileExtension(),
            MediaSchema::EXTENSION => $this->faker->fileExtension(),
            MediaSchema::MIME_TYPE => $this->faker->randomElement(MediaMimeTypeEnum::cases()),
            MediaSchema::SIZE => $this->faker->numberBetween(10000000, 99999999),
            MediaSchema::METADATA => null,
        ];
    }
}
