<?php

namespace Modules\Cart\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;

class CartDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);

        $cart = Cart::factory()->create();

        $items = CartItem::factory()->count(10)->create([CartItemSchema::CART_ID => $cart->{CartSchema::ID}]);
    }
}
