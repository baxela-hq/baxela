<?php

namespace Modules\Cart\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\CartItem\CartItemSchema;

class CartItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CartItem::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        return [
            CartItemSchema::CART_ID => $params[CartItemSchema::CART_ID] ?? Cart::inRandomOrder()?->first()?->id,
            CartItemSchema::VARIANT_ID => $params[CartItemSchema::VARIANT_ID] ?? $this->faker->numberBetween(1, 100),
            CartItemSchema::QUANTITY => $params[CartItemSchema::QUANTITY] ?? $this->faker->numberBetween(1, 20),
            CartItemSchema::PRICE_SNAPSHOT => $params[CartItemSchema::PRICE_SNAPSHOT] ?? $this->faker->numerify(1, 100),
            CartItemSchema::PRODUCT_NAME_SNAPSHOT => $params[CartItemSchema::PRODUCT_NAME_SNAPSHOT] ?? $this->faker->name(),
        ];
    }
}
