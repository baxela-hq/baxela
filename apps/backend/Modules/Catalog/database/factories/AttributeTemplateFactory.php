<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\AttributeTemplate;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateSchema;

class AttributeTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = AttributeTemplate::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            AttributeTemplateSchema::TITLE => $this->faker->unique()->words(2, true),
            AttributeTemplateSchema::DESCRIPTION => $this->faker->sentence(),
            AttributeTemplateSchema::IS_ACTIVE => $this->faker->boolean(),
            AttributeTemplateSchema::POSITION => $this->faker->numberBetween(1, 20),
        ];
    }
}
