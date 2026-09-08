<?php

namespace Modules\Media\Tests\Feature;

use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Schemas\User\UserSchema;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/media/admin'.$endpoint;
    }

    public function adminUser(): User
    {
        $user = User::factory()->create([
            UserSchema::IS_ACTIVE => true,
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => Role::SUPER_ADMIN,
            'guard_name' => GuardsEnum::WEB->value,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
