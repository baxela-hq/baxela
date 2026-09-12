<?php

namespace Modules\Catalog\Tests\Feature;

use Modules\Auth\Models\User;
use Modules\Core\Models\Language;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/catalog'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }
}

/**
 * The default English language row — translation payloads resolve their
 * `language` codes against it. Shared by this module's test files.
 */
function defaultLanguage(): Language
{
    return Language::query()->firstOrCreate(
        ['code' => 'en'],
        ['locale' => 'en', 'name' => 'English', 'code3' => 'eng', 'is_default' => true, 'is_active' => true],
    );
}
