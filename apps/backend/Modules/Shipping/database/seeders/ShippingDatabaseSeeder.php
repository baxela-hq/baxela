<?php

namespace Modules\Shipping\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Models\MethodTranslation;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Models\Zone;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Modules\Shipping\Schemas\Method\MethodTranslationSchema;
use Modules\Shipping\Schemas\Module;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Modules\Shipping\Schemas\Zone\ZoneSchema;

class ShippingDatabaseSeeder extends Seeder
{
    private CoreGatewayInterface $coreGateway;

    /** @var array<string, int|null> */
    private array $languageIds = [];

    /** @var array<string, array<string, float>> method code => zone key => price */
    private array $rates = [
        'standard' => ['domestic-us' => 5.00, 'rest-of-world' => 15.00],
        'express' => ['domestic-us' => 15.00, 'rest-of-world' => 35.00],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->coreGateway = App::make(CoreGatewayInterface::class);
        $moduleKey = Module::NAME_LOWER.'::seeder';

        $langs = $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->toArray();
        $masterLang = in_array('en', $langs, true) ? 'en' : ($langs[0] ?? 'en');

        $zones = Lang::get($moduleKey.'.zones', [], $masterLang) ?? [];
        $methods = Lang::get($moduleKey.'.methods', [], $masterLang) ?? [];

        Rate::query()->delete();
        MethodTranslation::query()->delete();
        Method::query()->delete();
        Zone::query()->delete();

        $zoneIds = $this->seedZones($zones);
        $this->seedMethods($methods, $zoneIds, $langs);
    }

    /**
     * @param  array<string, array{name: string, countries?: array<int, string>}>  $zones
     * @return array<string, int> zone key => id
     */
    private function seedZones(array $zones): array
    {
        $zoneIds = [];

        $position = 1;
        foreach ($zones as $key => $zone) {
            $record = Zone::query()->create([
                ZoneSchema::NAME => $zone['name'],
                ZoneSchema::IS_ACTIVE => true,
                ZoneSchema::POSITION => $position,
            ]);

            $record->countries()->sync($zone['countries'] ?? []);

            $zoneIds[$key] = $record->{ZoneSchema::ID};
            $position++;
        }

        return $zoneIds;
    }

    /**
     * @param  array<string, array{name: string, description?: string}>  $methods
     * @param  array<string, int>  $zoneIds
     * @param  array<int, string>  $langs
     */
    private function seedMethods(array $methods, array $zoneIds, array $langs): void
    {
        $moduleKey = Module::NAME_LOWER.'::seeder.methods';

        $position = 1;
        foreach ($methods as $code => $method) {
            $record = Method::query()->create([
                MethodSchema::CODE => $code,
                MethodSchema::IS_ACTIVE => true,
                MethodSchema::POSITION => $position,
            ]);

            foreach ($langs as $lang) {
                $translation = Lang::get($moduleKey.'.'.$code, [], $lang) ?? [];
                MethodTranslation::query()->create([
                    MethodTranslationSchema::METHOD_ID => $record->{MethodSchema::ID},
                    MethodTranslationSchema::LANGUAGE_ID => $this->languageId($lang),
                    MethodTranslationSchema::NAME => $translation['name'] ?? $method['name'],
                    MethodTranslationSchema::DESCRIPTION => $translation['description'] ?? null,
                ]);
            }

            foreach ($this->rates[$code] ?? [] as $zoneKey => $price) {
                if (! isset($zoneIds[$zoneKey])) {
                    continue;
                }

                Rate::query()->create([
                    RateSchema::METHOD_ID => $record->{MethodSchema::ID},
                    RateSchema::ZONE_ID => $zoneIds[$zoneKey],
                    RateSchema::PRICE => $price,
                ]);
            }

            $position++;
        }
    }

    private function languageId(string $code): ?int
    {
        if (! array_key_exists($code, $this->languageIds)) {
            $this->languageIds[$code] = $this->coreGateway->getLanguageIdByCode($code);
        }

        return $this->languageIds[$code];
    }
}
