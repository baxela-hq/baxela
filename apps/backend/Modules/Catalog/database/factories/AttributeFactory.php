<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Attribute;
use Modules\Catalog\Models\AttributeGroup;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTypeEnum;

class AttributeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Attribute::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            AttributeSchema::GROUP_ID => AttributeGroup::factory(),
            AttributeSchema::CODE => $this->faker->unique()->regexify('[a-z0-9]{5,10}'),
            AttributeSchema::DATA_TYPE => $this->faker->randomElement(AttributeTypeEnum::cases()),
            AttributeSchema::IS_FILTERABLE => $this->faker->boolean(),
            AttributeSchema::POSITION => $this->faker->numberBetween(1, 20),
        ];
    }
}
