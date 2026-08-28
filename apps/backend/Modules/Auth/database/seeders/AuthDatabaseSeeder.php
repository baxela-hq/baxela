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
        // $this->call([]);
        User::query()->create([
            UserSchema::PASSWORD => '12345678',
            UserSchema::EMAIL => 'meysam4n@gmail.com',
            UserSchema::EMAIL_VERIFIED_AT => now(),
            UserSchema::IS_ACTIVE => true,
            UserSchema::IS_ADMIN => true,
            UserSchema::COMMENT => null,
        ]);

        User::query()->create([
            UserSchema::PASSWORD => '12345678',
            UserSchema::EMAIL => 'maysam69@gmail.com',
            UserSchema::EMAIL_VERIFIED_AT => now(),
            UserSchema::IS_ACTIVE => true,
            UserSchema::IS_ADMIN => true,
            UserSchema::COMMENT => null,
        ]);

        User::query()->create([
            UserSchema::PASSWORD => '12345678',
            UserSchema::EMAIL => 'ai@test.com',
            UserSchema::EMAIL_VERIFIED_AT => now(),
            UserSchema::IS_ACTIVE => true,
            UserSchema::IS_ADMIN => true,
            UserSchema::COMMENT => null,
        ]);
    }
}
