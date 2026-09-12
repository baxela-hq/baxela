<?php

namespace Modules\Order\Tests\Feature;

use Modules\Auth\Models\User;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/order'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }
}
