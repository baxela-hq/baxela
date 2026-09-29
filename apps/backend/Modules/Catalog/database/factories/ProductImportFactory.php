<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\ProductImport;
use Modules\Catalog\Schemas\ProductImport\ProductImportSchema;
use Modules\Catalog\Schemas\ProductImport\ProductImportStatusEnum;
use Modules\Catalog\Schemas\ProductImport\ProductImportStrategyEnum;

class ProductImportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ProductImport::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            ProductImportSchema::USER_ID => $this->faker->randomDigitNotZero(),
            ProductImportSchema::MEDIA_ID => $this->faker->randomDigitNotZero(),
            ProductImportSchema::FILENAME => $this->faker->slug(2).'.csv',
            ProductImportSchema::STATUS => $this->faker->randomElement(ProductImportStatusEnum::cases()),
            ProductImportSchema::STRATEGY => $this->faker->randomElement(ProductImportStrategyEnum::cases()),
            ProductImportSchema::DRY_RUN => false,
            ProductImportSchema::TOTAL_ROWS => $this->faker->numberBetween(1, 100),
            ProductImportSchema::CREATED_COUNT => $this->faker->numberBetween(0, 50),
            ProductImportSchema::UPDATED_COUNT => $this->faker->numberBetween(0, 50),
            ProductImportSchema::SKIPPED_COUNT => $this->faker->numberBetween(0, 10),
            ProductImportSchema::FAILED_COUNT => $this->faker->numberBetween(0, 10),
            ProductImportSchema::DURATION_MS => $this->faker->numberBetween(100, 5000),
            ProductImportSchema::ERRORS => null,
        ];
    }
}
