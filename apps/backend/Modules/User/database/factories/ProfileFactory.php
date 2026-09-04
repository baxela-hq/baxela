<?php

namespace Modules\User\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\User\Models\Profile;
use Modules\User\Schemas\Profile\GenderEnum;
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
            ProfileSchema::FULL_NAME => $this->faker->name(),
            ProfileSchema::DISPLAY_NAME => $this->faker->optional()->userName(),
            ProfileSchema::BIO => $this->faker->optional()->sentence(),
            ProfileSchema::AVATAR => $this->faker->optional()->uuid(),
            ProfileSchema::GENDER => $this->faker->optional()->randomElement(GenderEnum::cases()),
            ProfileSchema::DATE_OF_BIRTH => $this->faker->optional()->dateTimeBetween('-70 years', '-18 years'),
        ];
    }
}
