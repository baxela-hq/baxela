<?php

namespace Modules\Auth\Tests\Feature;

use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Schemas\User\UserSchema;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/auth'.$endpoint;
    }

    public function superAdminRole(): Role
    {
        return Role::query()->firstOrCreate([
            'name' => Role::SUPER_ADMIN,
            'guard_name' => GuardsEnum::WEB->value,
        ]);
    }

    public function superAdminUser(): User
    {
        $user = User::factory()->create([
            UserSchema::IS_ACTIVE => true,
        ]);

        $user->assignRole($this->superAdminRole());

        return $user;
    }
}
