<?php

namespace Modules\Core\Tests\Feature;

use Modules\Auth\Models\User;

trait HelperTrait
{
    public function adminUrl(string $endpoint): string
    {
        return 'api/v1/core/admin'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }
}
