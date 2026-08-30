<?php

namespace Modules\Shipping\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Schemas\Rate\RateSchema;

class RateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Rate::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        return [
            RateSchema::METHOD_ID => $params[RateSchema::METHOD_ID] ?? 1,
            RateSchema::ZONE_ID => $params[RateSchema::ZONE_ID] ?? 1,
            RateSchema::PRICE => $this->faker->randomFloat(2, 0, 9999),
        ];
    }
}
