<?php

namespace Modules\Menu\Tests\Feature;

use Modules\Auth\Models\User;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/menu'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }
}
