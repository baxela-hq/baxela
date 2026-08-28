<?php

namespace Modules\Order\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderAddress;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;
use Modules\Order\Schemas\OrderAddress\OrderAddressTypeEnum;

class OrderAddressFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = OrderAddress::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        return [
            OrderAddressSchema::ORDER_ID => $params[OrderAddressSchema::ORDER_ID] ?? Order::inRandomOrder()?->first()?->id,
            OrderAddressSchema::TYPE => $this->faker->randomElement(OrderAddressTypeEnum::cases()),
            OrderAddressSchema::FULL_NAME => $this->faker->name(),
            OrderAddressSchema::PHONE => $this->faker->phoneNumber(),
            OrderAddressSchema::ADDRESS_LINE => $this->faker->address(),
            OrderAddressSchema::CITY => $this->faker->city(),
            OrderAddressSchema::POSTAL_CODE => $this->faker->postcode(),
        ];
    }
}
