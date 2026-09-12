<?php

namespace Modules\Shipping\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\User;
use Modules\Core\Models\Country;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Models\MethodTranslation;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Models\Zone;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Modules\Shipping\Schemas\Method\MethodTranslationSchema;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Modules\Shipping\Schemas\Zone\ZoneCountrySchema;
use Modules\Shipping\Schemas\Zone\ZoneSchema;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/shipping'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /**
     * An active method with a translation, priced for the given zone.
     * Country-scoped zones get a pivot row; a null country leaves the zone
     * as a "rest of world" fallback matching any country.
     */
    public function activeMethod(Zone $zone, float $price, string $name = 'Express'): Method
    {
        $method = Method::factory()->create([MethodSchema::IS_ACTIVE => true]);

        MethodTranslation::query()->create([
            MethodTranslationSchema::METHOD_ID => $method->id,
            MethodTranslationSchema::LANGUAGE_ID => 1,
            MethodTranslationSchema::NAME => $name,
        ]);

        Rate::factory()->create([
            RateSchema::METHOD_ID => $method->id,
            RateSchema::ZONE_ID => $zone->id,
            RateSchema::PRICE => $price,
        ]);

        return $method;
    }

    public function activeZone(?string $countryCode = null): Zone
    {
        $zone = Zone::factory()->create([ZoneSchema::IS_ACTIVE => true]);

        if ($countryCode !== null) {
            // The pivot alone is not enough: zone matching joins through the
            // countries table, so the referenced row must exist.
            Country::query()->firstOrCreate(
                ['code' => $countryCode],
                ['code3' => 'USA', 'name' => $countryCode],
            );

            DB::table(ZoneCountrySchema::TABLE)->insert([
                ZoneCountrySchema::ZONE_ID => $zone->id,
                ZoneCountrySchema::COUNTRY_CODE => $countryCode,
            ]);
        }

        return $zone;
    }
}
