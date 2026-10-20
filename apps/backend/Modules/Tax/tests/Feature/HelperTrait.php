<?php

namespace Modules\Tax\Tests\Feature;

use Modules\Auth\Models\User;
use Modules\Core\Models\Country;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/tax'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /**
     * CountryCodeRule validates against the seeded countries table, which
     * RefreshDatabase wipes — tests recreate the rows they reference.
     */
    public function seedCountries(string ...$codes): void
    {
        foreach ($codes as $code) {
            Country::query()->firstOrCreate(
                ['code' => $code],
                ['code3' => $code.'X', 'name' => 'Country '.$code],
            );
        }
    }

    public function taxClass(bool $isDefault = false, bool $isShipping = false): TaxClass
    {
        return TaxClass::query()->create([
            TaxClassSchema::NAME => uniqid('class-'),
            TaxClassSchema::IS_DEFAULT => $isDefault,
            TaxClassSchema::IS_SHIPPING => $isShipping,
        ]);
    }

    public function taxRate(TaxClass $class, string $country, string $rate, bool $isActive = true): TaxRate
    {
        return TaxRate::query()->create([
            TaxRateSchema::NAME => uniqid('rate-'),
            TaxRateSchema::COUNTRY_CODE => $country,
            TaxRateSchema::TAX_CLASS_ID => $class->id,
            TaxRateSchema::RATE => $rate,
            TaxRateSchema::IS_ACTIVE => $isActive,
        ]);
    }
}
