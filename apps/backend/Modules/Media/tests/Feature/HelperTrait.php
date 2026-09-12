<?php

namespace Modules\Media\Tests\Feature;

use Modules\Auth\Models\User;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/media/admin'.$endpoint;
    }

    public function adminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }
}
