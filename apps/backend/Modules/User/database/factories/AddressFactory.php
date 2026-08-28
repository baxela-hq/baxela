<?php

namespace Modules\User\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\User\Models\Address;
use Modules\User\Schemas\Address\AddressSchema;
use Modules\User\Schemas\Address\AddressTypeEnum;

class AddressFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Address::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            AddressSchema::USER_ID => $this->faker->randomDigitNotZero(),
            AddressSchema::TYPE => $this->faker->randomElement(AddressTypeEnum::cases()),
            AddressSchema::FULL_NAME => $this->faker->name(),
            AddressSchema::PHONE => $this->faker->phoneNumber(),
            AddressSchema::ADDRESS_LINE => $this->faker->address(),
            AddressSchema::CITY => $this->faker->city(),
            AddressSchema::POSTAL_CODE => $this->faker->postcode(),
            AddressSchema::IS_DEFAULT => $this->faker->boolean(),
        ];
    }
}
