<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    $this->productionLikeTenancy();
});

it('keeps each tenant to its own product, including identical slugs', function () {
    $context = app(TenantContext::class);
    $acme = $this->createTenant('acme', 'shop-acme.test');
    $other = $this->createTenant('other', 'shop-other.test');

    $makeProduct = function (int $tenantId, string $slug): Product {
        return app(TenantContext::class)->runForTenant($tenantId, function () use ($slug): Product {
            $product = Product::factory()->create([
                ProductSchema::STATUS => ProductStatusEnum::IN_STOCK,
                ProductSchema::IS_PUBLISHED => true,
            ]);

            $product->translations()->create([
                ProductTranslationSchema::LANGUAGE_ID => 1,
                ProductTranslationSchema::TITLE => 'Product '.$slug,
                ProductTranslationSchema::SLUG => $slug,
                ProductTranslationSchema::CONTENT => 'Body',
            ]);

            return $product;
        });
    };

    $acmeProduct = $makeProduct((int) $acme->getKey(), 'shared-slug');
    $otherProduct = $makeProduct((int) $other->getKey(), 'shared-slug');

    $acmeIds = $context->runForTenant((int) $acme->getKey(), fn () => Product::query()->pluck(ProductSchema::ID)->all());
    $otherIds = $context->runForTenant((int) $other->getKey(), fn () => Product::query()->pluck(ProductSchema::ID)->all());

    expect($acmeIds)->toBe([$acmeProduct->getKey()])
        ->and($otherIds)->toBe([$otherProduct->getKey()])
        ->and((int) $acmeProduct->getAttribute(TenantColumn::TENANT_ID))->toBe((int) $acme->getKey())
        ->and((int) $otherProduct->getAttribute(TenantColumn::TENANT_ID))->toBe((int) $other->getKey());

    $crossTenant = $context->runForTenant((int) $acme->getKey(), function () use ($otherProduct): array {
        return [
            Product::query()->find($otherProduct->getKey()),
            Product::query()->whereKey($otherProduct->getKey())->update([ProductSchema::IS_PUBLISHED => false]),
            Product::query()->whereKey($otherProduct->getKey())->delete(),
        ];
    });

    expect($crossTenant[0])->toBeNull()
        ->and($crossTenant[1])->toBe(0)
        ->and($crossTenant[2])->toBe(0);

    $this->getJson($this->onHost('shop-acme.test', '/api/v1/catalog/public/products/'.$otherProduct->getKey()))
        ->assertStatus(404);

    $this->getJson($this->onHost('shop-other.test', '/api/v1/catalog/public/products/'.$otherProduct->getKey()))
        ->assertOk()
        ->assertJsonPath('data.id', $otherProduct->getKey());
});
