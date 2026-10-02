<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\CatalogImport;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportEntityEnum;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportSchema;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportStatusEnum;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportStrategyEnum;

class CatalogImportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CatalogImport::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            CatalogImportSchema::USER_ID => $this->faker->randomDigitNotZero(),
            CatalogImportSchema::MEDIA_ID => $this->faker->randomDigitNotZero(),
            CatalogImportSchema::FILENAME => $this->faker->slug(2).'.csv',
            CatalogImportSchema::ENTITY => CatalogImportEntityEnum::PRODUCT,
            CatalogImportSchema::STATUS => $this->faker->randomElement(CatalogImportStatusEnum::cases()),
            CatalogImportSchema::STRATEGY => $this->faker->randomElement(CatalogImportStrategyEnum::cases()),
            CatalogImportSchema::DRY_RUN => false,
            CatalogImportSchema::TOTAL_ROWS => $this->faker->numberBetween(1, 100),
            CatalogImportSchema::CREATED_COUNT => $this->faker->numberBetween(0, 50),
            CatalogImportSchema::UPDATED_COUNT => $this->faker->numberBetween(0, 50),
            CatalogImportSchema::SKIPPED_COUNT => $this->faker->numberBetween(0, 10),
            CatalogImportSchema::FAILED_COUNT => $this->faker->numberBetween(0, 10),
            CatalogImportSchema::DURATION_MS => $this->faker->numberBetween(100, 5000),
            CatalogImportSchema::ERRORS => null,
            CatalogImportSchema::SUMMARY => null,
        ];
    }
}
