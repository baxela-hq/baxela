<?php

namespace Modules\Payment\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;
use Modules\Payment\Schemas\Payment\PaymentSchema;

class PaymentDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);

        // Both existing methods start active so a seeded store keeps taking
        // payments; gateways added later appear in the admin list disabled.
        foreach ([
            [PaymentMethodEnum::MANUAL, 10],
            [PaymentMethodEnum::STRIPE, 20],
        ] as [$method, $sortOrder]) {
            PaymentMethod::query()->firstOrCreate(
                [PaymentMethodSchema::METHOD => $method],
                [
                    PaymentMethodSchema::IS_ACTIVE => true,
                    PaymentMethodSchema::SORT_ORDER => $sortOrder,
                ],
            );
        }

        // Seed in the shop's default currency, like a real payment would
        $currencyId = app(CoreGatewayInterface::class)->getDefaultCurrency()?->id;

        Payment::factory()->count(5)->create([
            PaymentSchema::CURRENCY_ID => $currencyId,
        ]);
    }
}
