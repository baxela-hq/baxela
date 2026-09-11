<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Models\User;
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

        User::query()->create([
            UserSchema::PASSWORD => '12345678',
            UserSchema::EMAIL => 'admin@baxela.com',
            UserSchema::EMAIL_VERIFIED_AT => now(),
            UserSchema::IS_ACTIVE => true,
            UserSchema::COMMENT => null,
        ]);

        $this->call(AccessDatabaseSeeder::class);
    }
}
