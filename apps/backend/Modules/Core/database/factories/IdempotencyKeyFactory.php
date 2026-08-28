<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\IdempotencyKey;
use Modules\Core\Schemas\IdempotencyKeys\IdempotencyKeysSchema;

class IdempotencyKeyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = IdempotencyKey::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            IdempotencyKeysSchema::USER_ID => $this->faker->randomDigitNotZero(),
            IdempotencyKeysSchema::KEY => $this->faker->unique()->word(),
            IdempotencyKeysSchema::RESPONSE => [],
            IdempotencyKeysSchema::EXPIRED_AT => now()->addMinutes(1),
        ];
    }
}
