<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;

class AuthDatabaseSeeder extends Seeder
{
    /**
     * Seed the initial staff accounts. The password is never a committed
     * literal: it comes from ADMIN_USER_PASSWORD when set, otherwise a random
     * one is generated and reported to the console once, so nobody can sign
     * in with credentials shipped in the repository.
     *
     * @throws \Exception
     */
    public function run(): void
    {
        $password = env('ADMIN_USER_PASSWORD') ?? Str::password(24);

        collect(['info@baxela.com', 'admin@baxela.com'])->each(function (string $email) use ($password): void {
            User::query()->create([
                UserSchema::PASSWORD => Hash::make($password),
                UserSchema::EMAIL => $email,
                UserSchema::EMAIL_VERIFIED_AT => now(),
                UserSchema::IS_ACTIVE => true,
                UserSchema::COMMENT => null,
            ]);
        });

        if (! env('ADMIN_USER_PASSWORD') && $this->command) {
            $this->command->warn('Generated admin password (not persisted anywhere else, copy it now):');
            $this->command->info($password);
        }

        $this->call(AccessDatabaseSeeder::class);
    }
}
