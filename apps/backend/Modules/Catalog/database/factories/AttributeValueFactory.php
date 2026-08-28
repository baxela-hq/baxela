<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\AttributeValue;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;

class AttributeValueFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = AttributeValue::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            AttributeValueSchema::ATTRIBUTE_ID => null,
            AttributeValueSchema::POSITION => $this->faker->numberBetween(1, 20),
        ];
    }
}
