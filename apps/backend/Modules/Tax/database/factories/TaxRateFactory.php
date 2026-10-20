<?php

namespace Modules\Tax\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;

class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    public function definition(array $params = []): array
    {
        return [
            TaxRateSchema::NAME => $params[TaxRateSchema::NAME]
                ?? $this->faker->words(asText: true),
            TaxRateSchema::COUNTRY_CODE => $params[TaxRateSchema::COUNTRY_CODE]
                ?? 'US',
            TaxRateSchema::TAX_CLASS_ID => $params[TaxRateSchema::TAX_CLASS_ID]
                ?? TaxClass::factory(),
            TaxRateSchema::RATE => $params[TaxRateSchema::RATE]
                ?? $this->faker->randomFloat(4, 0, 30),
            TaxRateSchema::IS_ACTIVE => $params[TaxRateSchema::IS_ACTIVE]
                ?? true,
        ];
    }
}
