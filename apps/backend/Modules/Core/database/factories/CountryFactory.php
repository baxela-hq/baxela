<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Country;
use Modules\Core\Schemas\Country\CountrySchema;

class CountryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Country::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            CountrySchema::CODE => $this->faker->unique()->countryCode(),
            CountrySchema::CODE3 => $this->faker->unique()->countryISOAlpha3(),
            CountrySchema::NAME => $this->faker->country(),
        ];
    }
}
