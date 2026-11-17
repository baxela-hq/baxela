<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Option;
use Modules\Catalog\Schemas\Option\OptionSchema;

/** @extends Factory<Option> */
class OptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Option::class;

    /**
     * Define the model's default state. The options table carries only
     * position; names/slugs live in option_translations.
     */
    public function definition(): array
    {
        return [
            OptionSchema::POSITION => $this->faker->randomNumber(),
        ];
    }
}
