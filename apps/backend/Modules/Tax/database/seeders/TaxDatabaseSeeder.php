<?php

namespace Modules\Tax\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;

class TaxDatabaseSeeder extends Seeder
{
    /**
     * Dev-only sample data: the four classes the platform assumes
     * (exactly one default, exactly one shipping) plus US sample rates.
     * Class names are labels — the flags and rates carry behavior.
     */
    public function run(): void
    {
        TaxRate::query()->delete();
        TaxClass::query()->delete();

        $standard = TaxClass::query()->create([
            TaxClassSchema::NAME => 'Standard',
            TaxClassSchema::IS_DEFAULT => true,
            TaxClassSchema::IS_SHIPPING => false,
        ]);

        $reduced = TaxClass::query()->create([
            TaxClassSchema::NAME => 'Reduced',
            TaxClassSchema::IS_DEFAULT => false,
            TaxClassSchema::IS_SHIPPING => false,
        ]);

        $exempt = TaxClass::query()->create([
            TaxClassSchema::NAME => 'Exempt',
            TaxClassSchema::IS_DEFAULT => false,
            TaxClassSchema::IS_SHIPPING => false,
        ]);

        $shipping = TaxClass::query()->create([
            TaxClassSchema::NAME => 'Shipping',
            TaxClassSchema::IS_DEFAULT => false,
            TaxClassSchema::IS_SHIPPING => true,
        ]);

        collect([
            [$standard, 'US Standard 8.5%', '8.5'],
            [$reduced, 'US Reduced 4%', '4.0'],
            // explicit zero rate — the exempt class is zero by configuration,
            // not by its name
            [$exempt, 'US Exempt 0%', '0.0'],
            [$shipping, 'US Shipping 8.5%', '8.5'],
        ])->each(fn (array $row) => TaxRate::query()->create([
            TaxRateSchema::NAME => $row[1],
            TaxRateSchema::COUNTRY_CODE => 'US',
            TaxRateSchema::TAX_CLASS_ID => $row[0]->id,
            TaxRateSchema::RATE => $row[2],
            TaxRateSchema::IS_ACTIVE => true,
        ]));
    }
}
