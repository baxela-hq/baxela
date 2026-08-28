<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\AttributeGroup;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;

class AttributeGroupFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = AttributeGroup::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            AttributeGroupSchema::POSITION => $this->faker->numberBetween(1, 20),
        ];
    }
}
