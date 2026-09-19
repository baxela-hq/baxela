<?php

namespace Modules\Order\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderAddress;
use Modules\Order\Models\OrderItem;
use Modules\Order\Schemas\Order\OrderSchema;

class OrderDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);

        // Seed in the shop's default currency, like a real checkout would
        $currencyId = app(CoreGatewayInterface::class)->getDefaultCurrency()?->id;

        $order = Order::factory()->create([
            OrderSchema::CURRENCY_ID => $currencyId,
        ]);
        $order->items()->saveMany(OrderItem::factory()->count(3)->create());
        $order->addresses()->saveMany(OrderAddress::factory()->count(2)->create());
    }
}
