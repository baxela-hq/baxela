<?php

namespace Modules\Payment\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Payment\Models\Payment;
use Modules\Payment\Schemas\Payment\PaymentSchema;

class PaymentDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);

        // Seed in the shop's default currency, like a real payment would
        $currencyId = app(CoreGatewayInterface::class)->getDefaultCurrency()?->id;

        Payment::factory()->count(5)->create([
            PaymentSchema::CURRENCY_ID => $currencyId,
        ]);
    }
}
