<?php

namespace Modules\Media\Tests\Feature;

use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/media/admin'.$endpoint;
    }

    public function adminUser(): User
    {
        return User::factory()->create([
            UserSchema::IS_ADMIN => true,
            UserSchema::IS_ACTIVE => true,
        ]);
    }
}
