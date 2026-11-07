<?php

namespace Modules\Setting\Tests\Feature;

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Modules\Auth\Models\User;
use Modules\Setting\Models\Setting;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/setting'.$endpoint;
    }

    /** The Transfer module's endpoints hosting this module's export/import. */
    public function transferUrl(string $endpoint): string
    {
        return 'api/v1/transfer'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** A translatable setting plus the two id-reference settings. */
    public function seedTransferSettings(int $languageId, string $currencyId): array
    {
        $title = Setting::factory()->create(['name' => 'website_title', 'value' => 'Old Shop']);
        $title->translations()->create(['language_id' => $languageId, 'value' => 'Old Shop EN']);

        $language = Setting::factory()->create(['name' => 'language_id', 'value' => (string) $languageId]);
        $currency = Setting::factory()->create(['name' => 'currency_id', 'value' => $currencyId]);

        return ['title' => $title, 'language' => $language, 'currency' => $currency];
    }

    /**
     * Export through the module's own data endpoint and return the
     * decoded envelope.
     *
     * @return array<string, mixed>
     */
    public function exportedSettingDataFile(): array
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
