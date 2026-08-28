<?php

namespace Modules\Order\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderAddress;
use Modules\Order\Models\OrderItem;

class OrderDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);

        $order = Order::factory()->create();
        $order->items()->saveMany(OrderItem::factory()->count(3)->create());
        $order->addresses()->saveMany(OrderAddress::factory()->count(2)->create());
    }
}
