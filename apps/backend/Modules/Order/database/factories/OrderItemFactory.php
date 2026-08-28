<?php

namespace Modules\Order\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;

class OrderItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = OrderItem::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        return [
            OrderItemSchema::ORDER_ID => $params[OrderItemSchema::ORDER_ID] ?? Order::inRandomOrder()?->first()?->id,
            OrderItemSchema::VARIANT_ID => $this->faker->numberBetween(1, 100),
            OrderItemSchema::PRICE_SNAPSHOT => $params[OrderItemSchema::PRICE_SNAPSHOT] ?? $this->faker->randomNumber(),
            OrderItemSchema::PRODUCT_NAME_SNAPSHOT => $params[OrderItemSchema::PRODUCT_NAME_SNAPSHOT] ?? $this->faker->name(),
            OrderItemSchema::QUANTITY => $params[OrderItemSchema::QUANTITY] ?? $this->faker->numberBetween(1, 10),
        ];
    }
}
