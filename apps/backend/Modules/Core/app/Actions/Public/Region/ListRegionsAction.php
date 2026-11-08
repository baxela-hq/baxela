<?php

namespace Modules\Core\Actions\Public\Region;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Region;
use Modules\Core\Schemas\Region\RegionSchema;

class ListRegionsAction
{
    /**
     * All seeded regions for a country (gap-C1a D5): deterministic — the
     * full seeded set, not a filtered subset; cached per country.
     *
     * @return array<int, array{code: string, name: string}>
     */
    public function handle(string $countryCode): array
    {
        return Cache::remember(
            'core.regions.'.strtoupper($countryCode),
            now()->addHour(),
            fn (): array => Region::query()
                ->where(RegionSchema::COUNTRY_CODE, strtoupper($countryCode))
                ->orderBy(RegionSchema::NAME)
                ->get([RegionSchema::CODE, RegionSchema::NAME])
                ->map(fn (Region $region): array => [
                    RegionSchema::CODE => $region->{RegionSchema::CODE},
                    RegionSchema::NAME => $region->{RegionSchema::NAME},
                ])
                ->all(),
        );
    }
}
