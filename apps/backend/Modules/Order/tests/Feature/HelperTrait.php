<?php

namespace Modules\Order\Tests\Feature;

use Modules\Auth\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/order'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /**
     * A customer account plus one catalog variant — the cross-module
     * references an order history needs to exist locally.
     *
     * @return array{customer: User, variant: Variant}
     */
    public function seedOrderTransferData(): array
    {
        $customer = User::factory()->create(['email' => 'customer@example.com']);
        $product = Product::query()->create(['type' => 'simple', 'status' => 'in_stock', 'is_published' => true]);
        $variant = Variant::factory()->create(['product_id' => $product->id]);

        return ['customer' => $customer, 'variant' => $variant];
    }

    /**
     * Export through the module's own data endpoint and return the
     * decoded envelope.
     *
     * @return array<string, mixed>
     */
    public function exportedOrderDataFile(): array
    {
        return json_decode(
            $this->getJson($this->baseUrl('/admin/data/export'))->assertOk()->streamedContent(),
            true
        );
    }
}
