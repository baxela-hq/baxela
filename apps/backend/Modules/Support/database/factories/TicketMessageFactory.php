<?php

namespace Modules\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Support\Models\TicketMessage;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;
use Modules\Support\Schemas\TicketMessage\TicketSenderEnum;

class TicketMessageFactory extends Factory
{
    /**
     * The factory's corresponding model.
     */
    protected $model = TicketMessage::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            TicketMessageSchema::TICKET_ID => $this->faker->randomDigitNotZero(),
            TicketMessageSchema::USER_ID => $this->faker->randomDigitNotZero(),
            TicketMessageSchema::SENDER => $this->faker->randomElement(TicketSenderEnum::cases()),
            TicketMessageSchema::BODY => $this->faker->paragraphs(asText: true),
        ];
    }
}
