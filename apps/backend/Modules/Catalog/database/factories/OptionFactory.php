<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Option;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Option\OptionSchema;

/** @extends Factory<Option> */
class OptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Option::class;

    /**
     * Define the model's default state.
     */
    /** @param  array<string, mixed>  $params */
    public function definition(array $params = []): array
    {
        return [
            OptionSchema::PRODUCT_ID => $params[OptionSchema::PRODUCT_ID] ?? Product::inRandomOrder()?->first()?->id,
            OptionSchema::NAME => $this->faker->name(),
            OptionSchema::SLUG => $this->faker->slug(),
            OptionSchema::POSITION => $this->faker->randomNumber(),
        ];
    }
}
