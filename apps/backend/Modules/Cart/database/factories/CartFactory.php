<?php

namespace Modules\Cart\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Cart\Models\Cart;
use Modules\Cart\Schemas\Cart\CartSchema;

/** @extends Factory<Cart> */
class CartFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Cart::class;

    /**
     * Define the model's default state.
     */
    /** @param  array<string, mixed>  $params */
    public function definition(array $params = []): array
    {
        return [
            CartSchema::USER_ID => $params[CartSchema::USER_ID] ?? $this->faker->numberBetween(1, 100),
        ];
    }
}
