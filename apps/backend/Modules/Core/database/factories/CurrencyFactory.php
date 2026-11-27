<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Currency;
use Modules\Core\Schemas\Currency\CurrencySchema;

/** @extends Factory<Currency> */
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
            // The database enforces at most one base currency (§4.5); a
            // factory row must not randomly claim the platform default.
            CurrencySchema::IS_DEFAULT => false,
            CurrencySchema::EXCHANGE_RATE => '1.00000000',
            CurrencySchema::IS_ACTIVE => true,
        ];
    }
}
