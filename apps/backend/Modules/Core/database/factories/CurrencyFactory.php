<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Currency;
use Modules\Core\Schemas\Currency\CurrencySchema;

class CurrencyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Currency::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            CurrencySchema::CODE => $this->faker->unique()->currencyCode,
            CurrencySchema::NAME => $this->faker->currencyCode,
            CurrencySchema::IS_DEFAULT => $this->faker->boolean,
        ];
    }
}
