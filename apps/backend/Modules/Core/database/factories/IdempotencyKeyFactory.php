<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Core\Models\IdempotencyKey;
use Modules\Core\Schemas\IdempotencyKeys\IdempotencyKeysSchema;

/** @extends Factory<IdempotencyKey> */
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
            IdempotencyKeysSchema::PRINCIPAL_KEY => 'user:'.$this->faker->randomDigitNotZero(),
            IdempotencyKeysSchema::KEY => $this->faker->unique()->word(),
            IdempotencyKeysSchema::STATUS => IdempotencyKeysSchema::STATUS_PROCESSING,
            IdempotencyKeysSchema::CLAIM_TOKEN => (string) Str::uuid(),
            IdempotencyKeysSchema::RESPONSE => [],
            IdempotencyKeysSchema::EXPIRES_AT => now()->addMinutes(1),
        ];
    }
}
