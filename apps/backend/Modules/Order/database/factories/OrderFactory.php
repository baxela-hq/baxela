<?php

namespace Modules\Order\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;

class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        return [
            OrderSchema::USER_ID => $params[OrderSchema::USER_ID] ?? $this->faker->unique()->numberBetween(1, 20),
            OrderSchema::TOTAL_AMOUNT => $this->faker->randomFloat(2, 0, 9999),
            OrderSchema::STATUS => $this->faker->randomElement(OrderStatusEnum::cases()),
            OrderSchema::EXPIRES_AT => $this->faker->dateTimeBetween('now', '+1 month'),
        ];
    }
}
