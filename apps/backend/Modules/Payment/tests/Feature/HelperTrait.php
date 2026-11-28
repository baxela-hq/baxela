<?php

namespace Modules\Payment\Tests\Feature;

use Modules\Auth\Models\User;
use Modules\Order\Models\Order;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        // The webhook routes carry {tenantSlug} (multi-tenancy §3.1 B2):
        // the tests resolve the seeded default store — production seeds
        // the same row (TenantDatabaseSeeder, §4.1).
        if (str_contains($endpoint, '/webhook/')) {
            \Modules\Tenant\Models\Tenant::query()->firstOrCreate(
                ['slug' => 'default'],
                ['name' => 'Default Store', 'email' => 'store@localhost', 'status' => 'active'],
            );
        }

        return 'api/v1/payment'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /**
     * An order for the payment ledger to reference.
     *
     * @return array{order: Order}
     */
    public function seedOrderWithPayment(): array
    {
        $customer = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_code' => 'PAY-TEST-0001',
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        return ['order' => $order];
    }

    /**
     * Export through the module's own data endpoint and return the
     * decoded envelope.
     *
     * @return array<string, mixed>
     */
    public function exportedPaymentDataFile(): array
    {
        return json_decode(
            $this->getJson($this->baseUrl('/admin/data/export'))->assertOk()->streamedContent(),
            true
        );
    }
}
