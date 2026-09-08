<?php

namespace Modules\Contact\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contact\Models\ContactMessage;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;
use Modules\Contact\Schemas\ContactMessage\ContactMessageStatusEnum;

class ContactMessageFactory extends Factory
{
    /**
     * The factory's corresponding model.
     */
    protected $model = ContactMessage::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            ContactMessageSchema::NAME => $this->faker->name(),
            ContactMessageSchema::EMAIL => $this->faker->safeEmail(),
            ContactMessageSchema::PHONE => $this->faker->optional()->e164PhoneNumber(),
            ContactMessageSchema::SUBJECT => $this->faker->sentence(),
            ContactMessageSchema::CONTENT => $this->faker->paragraphs(asText: true),
            ContactMessageSchema::STATUS => $this->faker->randomElement(ContactMessageStatusEnum::cases())->value,
            ContactMessageSchema::IP_ADDRESS => $this->faker->optional()->ipv4(),
        ];
    }
}
