<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Schemas\User\UserSchema;

class AuthDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @throws \Exception
     */
    public function run(): void
    {
        User::query()->create([
            UserSchema::PASSWORD => '12345678',
            UserSchema::EMAIL => 'info@baxela.com',
            UserSchema::EMAIL_VERIFIED_AT => now(),
            UserSchema::IS_ACTIVE => true,
            UserSchema::COMMENT => null,
        ]);

        $admin = User::query()->create([
            UserSchema::PASSWORD => '12345678',
            UserSchema::EMAIL => 'admin@baxela.com',
            UserSchema::EMAIL_VERIFIED_AT => now(),
            UserSchema::IS_ACTIVE => true,
            UserSchema::COMMENT => null,
        ]);

        $admin->assignRole(Role::query()->firstOrCreate([
            'name' => Role::SUPER_ADMIN,
            'guard_name' => GuardsEnum::WEB->value,
        ]));

        $this->call(AccessDatabaseSeeder::class);
    }
}
