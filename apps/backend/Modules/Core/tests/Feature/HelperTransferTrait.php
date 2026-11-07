<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Modules\Auth\Models\User;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Core\Schemas\Language\LanguageSchema;

trait HelperTransferTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/core'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** Two languages (en default), one default currency, two countries. */
    public function seedCoreTransferData(): void
    {
        Language::factory()->create([
            LanguageSchema::LOCALE => 'en',
            LanguageSchema::CODE => 'en',
            LanguageSchema::CODE3 => 'eng',
            LanguageSchema::NAME => 'English',
            LanguageSchema::IS_DEFAULT => true,
            LanguageSchema::IS_ACTIVE => true,
            LanguageSchema::POSITION => 1,
        ]);
        Language::factory()->create([
            LanguageSchema::LOCALE => 'de',
            LanguageSchema::CODE => 'de',
            LanguageSchema::CODE3 => 'deu',
            LanguageSchema::NAME => 'German',
            LanguageSchema::IS_DEFAULT => false,
            LanguageSchema::IS_ACTIVE => true,
            LanguageSchema::POSITION => 2,
        ]);
        Currency::factory()->create([
            CurrencySchema::CODE => 'USD',
            CurrencySchema::NAME => 'US Dollar',
            CurrencySchema::IS_DEFAULT => true,
            CurrencySchema::IS_ACTIVE => true,
        ]);
        Country::factory()->create(['code' => 'DE', 'code3' => 'DEU', 'name' => 'Germany']);
        Country::factory()->create(['code' => 'US', 'code3' => 'USA', 'name' => 'United States']);
    }

    /**
     * Export through the module's own data endpoint and return the
     * decoded envelope.
     *
     * @return array<string, mixed>
     */
    public function exportedCoreFile(): array
    {
        return json_decode(
            $this->getJson($this->baseUrl('/admin/data/export'))->assertOk()->streamedContent(),
            true
        );
    }

    /** The multipart file field for the data-import endpoints. */
    public function transferFile(string $content, string $name = 'transfer.json'): File
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
