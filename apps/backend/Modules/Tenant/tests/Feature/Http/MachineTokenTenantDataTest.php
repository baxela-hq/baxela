<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    $this->productionLikeTenancy();
});

it('only exposes the token tenant\'s products, whatever tenant headers claim', function () {
    $owner = $this->plainUser();
    $acme = $this->createTenant('acme', 'shop-acme.test');
    $other = $this->createTenant('other', 'shop-other.test');

    $makeProduct = fn (int $tenantId): Product => app(TenantContext::class)->runForTenant(
        $tenantId,
        fn (): Product => Product::factory()->create(),
    );

    $acmeProduct = $makeProduct((int) $acme->getKey());
    $otherProduct = $makeProduct((int) $other->getKey());

    $headers = [
        'X-Tenant-Id' => (string) $other->getKey(),
        'X-Tenant-Host' => 'shop-other.test',
    ];
    $token = $this->machineToken($owner, (int) $acme->getKey());

    $response = $this->withToken($token)
        ->getJson('/api/v1/webhooks/machine/products', $headers)
        ->assertOk();

    /** @var array<int, array<string, mixed>> $data */
    $data = $response->json('data');
    $ids = collect($data)->pluck(ProductSchema::ID)->all();

    expect($ids)->toBe([$acmeProduct->getKey()])
        ->and($ids)->not->toContain($otherProduct->getKey())
        ->and(app(TenantContext::class)->tenantId())->toBe((int) $acme->getKey());

    $this->withToken($this->machineToken($owner, (int) $acme->getKey()))
        ->getJson('/api/v1/webhooks/machine/products/'.$otherProduct->getKey(), $headers)
        ->assertStatus(404);
});
