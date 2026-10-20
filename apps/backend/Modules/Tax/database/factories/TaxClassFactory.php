<?php

namespace Modules\Tax\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;

class TaxClassFactory extends Factory
{
    protected $model = TaxClass::class;

    public function definition(array $params = []): array
    {
        return [
            TaxClassSchema::NAME => $params[TaxClassSchema::NAME]
                ?? $this->faker->unique()->words(asText: true),
            TaxClassSchema::IS_DEFAULT => $params[TaxClassSchema::IS_DEFAULT]
                ?? false,
            TaxClassSchema::IS_SHIPPING => $params[TaxClassSchema::IS_SHIPPING]
                ?? false,
        ];
    }
}
