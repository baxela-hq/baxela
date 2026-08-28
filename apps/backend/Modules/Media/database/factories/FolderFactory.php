<?php

namespace Modules\Media\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Media\Models\Folder;
use Modules\Media\Schemas\Folder\FolderSchema;

class FolderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Folder::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            FolderSchema::USER_ID => $this->faker->randomDigitNotZero(),
            FolderSchema::PARENT_ID => null,
            FolderSchema::NAME => $this->faker->word(),
            FolderSchema::POSITION => $this->faker->numberBetween(1, 255),
        ];
    }
}
