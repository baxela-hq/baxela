<?php

namespace Modules\Auth\Database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Schemas\User\UserSchema;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(array $params = []): array
    {
        return [
            'email' => $params['email'] ?? fake()->unique()->safeEmail(),
            'email_verified_at' => $params['email_verified_at'] ?? now(),
            'password' => fake()->password(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            UserSchema::IS_ACTIVE => true,
        ]);
    }

    /**
     * Active user carrying the super-admin role — the standard admin-side
     * test actor.
     */
    public function superAdmin(): static
    {
        return $this->active()->afterCreating(function (User $user): void {
            $role = Role::query()->firstOrCreate([
                'name' => Role::SUPER_ADMIN,
                'guard_name' => GuardsEnum::WEB->value,
            ]);

            $user->assignRole($role);
        });
    }
}
