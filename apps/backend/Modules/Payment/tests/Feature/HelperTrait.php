<?php

namespace Modules\Payment\Tests\Feature;

use Modules\Auth\Models\User;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Order\Models\Order;
use Modules\Tenant\Models\Tenant;

/**
 * Point the process tenant context at the same default-store row the
 * webhook route resolves. PostgreSQL sequences are not rolled back by
 * RefreshDatabase, so the row's id is not necessarily 1 by the time
 * these tests run; store rows must be stamped with the resolved id.
 */
function useDefaultWebhookTenant(): void
{
    $tenant = Tenant::query()->firstOrCreate(
        ['slug' => 'default'],
        ['name' => 'Default Store', 'email' => 'store@localhost', 'status' => 'active'],
    );

    app(TenantContext::class)->forTenant((int) $tenant->getKey());
}

trait HelperTrait
{
    public function useDefaultWebhookTenant(): void
    {
        useDefaultWebhookTenant();
    }

    public function baseUrl(string $endpoint): string
    {
        // The webhook routes carry {tenantSlug} (multi-tenancy §3.1 B2):
        // the tests resolve the seeded default store — production seeds
        // the same row (TenantDatabaseSeeder, §4.1).
        if (str_contains($endpoint, '/webhook/')) {
            $this->useDefaultWebhookTenant();
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
