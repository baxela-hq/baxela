<?php

namespace Modules\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Support\Models\Ticket;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\Ticket\TicketStatusEnum;

class TicketFactory extends Factory
{
    /**
     * The factory's corresponding model.
     */
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            TicketSchema::USER_ID => $this->faker->randomDigitNotZero(),
            TicketSchema::ORDER_CODE => $this->faker->optional()->bothify('ORD-#####'),
            TicketSchema::SUBJECT => $this->faker->sentence(),
            TicketSchema::STATUS => $this->faker->randomElement(TicketStatusEnum::cases()),
            TicketSchema::LAST_MESSAGE_AT => $this->faker->dateTimeThisMonth(),
        ];
    }
}
