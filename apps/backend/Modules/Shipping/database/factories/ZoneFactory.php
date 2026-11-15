<?php

namespace Modules\Shipping\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shipping\Models\Zone;
use Modules\Shipping\Schemas\Zone\ZoneSchema;

/** @extends Factory<Zone> */
class ZoneFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Zone::class;

    /**
     * Define the model's default state.
     */
    /** @param  array<string, mixed>  $params */
    public function definition(array $params = []): array
    {
        return [
            ZoneSchema::NAME => $params[ZoneSchema::NAME] ?? $this->faker->unique()->words(asText: true),
            ZoneSchema::IS_ACTIVE => $this->faker->boolean(),
            ZoneSchema::POSITION => $this->faker->numberBetween(1, 20),
        ];
    }
}
