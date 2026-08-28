<?php

namespace Modules\Payment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payment\Models\Payment;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;

class PaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            PaymentSchema::ORDER_ID => $this->faker->numberBetween(1, 50),
            PaymentSchema::TRANSACTION_ID => null,
            PaymentSchema::METHOD => $this->faker->randomElement(PaymentMethodEnum::cases()),
            PaymentSchema::AMOUNT => $this->faker->randomNumber(),
            PaymentSchema::STATUS => $this->faker->randomElement(PaymentStatusEnum::cases()),
        ];
    }
}
