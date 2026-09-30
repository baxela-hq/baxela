<?php

namespace Modules\Inventory\Tests\Feature;

use Modules\Auth\Models\User;
use Modules\Core\Models\Language;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/inventory'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }
}

/**
 * The default English language row — stock rows eager-load product
 * translations whose `language` codes resolve against it.
 */
function defaultLanguage(): Language
{
    return Language::query()->firstOrCreate(
        ['code' => 'en'],
        ['locale' => 'en', 'name' => 'English', 'code3' => 'eng', 'is_default' => true, 'is_active' => true],
    );
}
