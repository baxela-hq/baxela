<?php

namespace Modules\User\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\User\Models\Profile;
use Modules\User\Schemas\Profile\ProfileSchema;

class ProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Profile::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            ProfileSchema::USER_ID => $this->faker->unique()->randomNumber(),
            ProfileSchema::FIRST_NAME => $this->faker->firstName(),
            ProfileSchema::LAST_NAME => $this->faker->lastName(),
        ];
    }
}
