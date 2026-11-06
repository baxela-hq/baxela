<?php

namespace Modules\Transfer\Tests\Feature;

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
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
        return 'api/v1/transfer'.$endpoint;
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

    /** Compact but complete tax data: two classes, one rate. */
    public function seedTransferTax(): array
    {
        $default = TaxClass::query()->create([
            TaxClassSchema::NAME => 'standard',
            TaxClassSchema::IS_DEFAULT => true,
            TaxClassSchema::IS_SHIPPING => false,
        ]);
        $reduced = TaxClass::query()->create([
            TaxClassSchema::NAME => 'reduced',
            TaxClassSchema::IS_DEFAULT => false,
            TaxClassSchema::IS_SHIPPING => true,
        ]);
        $rate = TaxRate::query()->create([
            TaxRateSchema::NAME => 'VAT',
            TaxRateSchema::COUNTRY_CODE => 'DE',
            TaxRateSchema::TAX_CLASS_ID => $default->id,
            TaxRateSchema::RATE => '19.0000',
            TaxRateSchema::IS_ACTIVE => true,
        ]);

        return ['default' => $default, 'reduced' => $reduced, 'rate' => $rate];
    }

    /**
     * Export the (seeded) tax data through the API and return the
     * decoded envelope — the round-trip input for the import endpoints.
     *
     * @return array<string, mixed>
     */
    public function exportedTaxFile(): array
    {
        return json_decode(
            $this->getJson($this->baseUrl('/admin/exports/tax'))->assertOk()->streamedContent(),
            true
        );
    }

    /** The multipart file field of an import request. */
    public function transferFile(string $content, string $name = 'transfer.json'): File
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
