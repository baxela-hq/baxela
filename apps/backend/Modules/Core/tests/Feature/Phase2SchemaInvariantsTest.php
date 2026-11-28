<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTypeEnum;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Core\Schemas\IdempotencyKeys\IdempotencyKeysSchema;
use Modules\Core\Schemas\Shared\PrimaryKeyColumn;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Schemas\TransferRemap\TransferRemapSchema;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Media\Schemas\Folder\FolderSchema;
use Modules\Order\Schemas\Document\DocumentSequenceSchema;
use Modules\Payment\Schemas\PaymentDriverCredential\PaymentDriverCredentialSchema;
use Modules\Setting\Schemas\Setting\SettingGroupEnum;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Schemas\Setting\SettingTypeEnum;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;

uses(RefreshDatabase::class);

it('rejects a child row whose parent belongs to another store', function () {
    // Each rejected write runs in its own savepoint: PostgreSQL aborts the
    // surrounding RefreshDatabase transaction on a constraint error.
    /** @param Closure(): bool $write */
    $rejects = static function (Closure $write): bool {
        try {
            DB::transaction(static fn (): bool => $write());

            return false;
        } catch (QueryException) {
            return true;
        }
    };

    $product = fn (int $tenant, int $id): array => [
        TenantColumn::TENANT_ID => $tenant,
        PrimaryKeyColumn::ID => $id,
        ProductSchema::TYPE => ProductTypeEnum::SIMPLE->value,
        ProductSchema::STATUS => ProductStatusEnum::IN_STOCK->value,
        ProductSchema::BRAND_ID => null,
        ProductSchema::TAX_CLASS_ID => null,
    ];

    DB::table(ProductSchema::TABLE)->insert($product(1, 101));
    DB::table(ProductSchema::TABLE)->insert($product(2, 102));

    expect($rejects(fn () => DB::table(VariantSchema::TABLE)->insert([
        TenantColumn::TENANT_ID => 2,
        PrimaryKeyColumn::ID => 201,
        VariantSchema::PRODUCT_ID => 101,
        VariantSchema::SKU => 'cross-tenant',
        VariantSchema::PRICE => 10,
        VariantSchema::QUANTITY => 1,
    ])))->toBeTrue();

    expect(DB::table(VariantSchema::TABLE)->count())->toBe(0);
});

it('allows identical case-insensitive keys in two stores but not in one', function () {
    foreach ([1, 2] as $tenantId) {
        DB::table(ProductSchema::TABLE)->insert([
            TenantColumn::TENANT_ID => $tenantId,
            PrimaryKeyColumn::ID => 100 + $tenantId,
            ProductSchema::TYPE => ProductTypeEnum::SIMPLE->value,
            ProductSchema::STATUS => ProductStatusEnum::IN_STOCK->value,
        ]);
    }

    // Each rejected write runs in its own savepoint: PostgreSQL aborts the
    // surrounding RefreshDatabase transaction on a constraint error.
    /** @param Closure(): bool $write */
    $rejects = static function (Closure $write): bool {
        try {
            DB::transaction(static fn (): bool => $write());

            return false;
        } catch (QueryException) {
            return true;
        }
    };

    $variant = fn (int $tenant, string $sku): array => [
        TenantColumn::TENANT_ID => $tenant,
        PrimaryKeyColumn::ID => 300 + $tenant,
        VariantSchema::PRODUCT_ID => 100 + $tenant,
        VariantSchema::SKU => $sku,
        VariantSchema::PRICE => 10,
        VariantSchema::QUANTITY => 1,
    ];

    DB::table(VariantSchema::TABLE)->insert($variant(1, 'SAME-SKU'));
    DB::table(VariantSchema::TABLE)->insert($variant(2, 'same-sku'));

    expect($rejects(fn () => DB::table(VariantSchema::TABLE)->insert(
        [...$variant(1, 'x'), PrimaryKeyColumn::ID => 399, VariantSchema::SKU => 'same-SKU'],
    )))->toBeTrue();

    DB::table(CouponSchema::TABLE)->insert([
        TenantColumn::TENANT_ID => 1,
        CouponSchema::CODE => 'SAVE10',
        CouponSchema::NAME => 'A',
        CouponSchema::TYPE => 'fixed',
        CouponSchema::VALUE => 1,
    ]);
    DB::table(CouponSchema::TABLE)->insert([
        TenantColumn::TENANT_ID => 2,
        CouponSchema::CODE => 'SAVE10',
        CouponSchema::NAME => 'B',
        CouponSchema::TYPE => 'fixed',
        CouponSchema::VALUE => 1,
    ]);

    expect($rejects(fn () => DB::table(CouponSchema::TABLE)->insert([
        TenantColumn::TENANT_ID => 1,
        CouponSchema::CODE => 'save10',
        CouponSchema::NAME => 'duplicate',
        CouponSchema::TYPE => 'fixed',
        CouponSchema::VALUE => 1,
    ])))->toBeTrue();
});

it('applies root-folder and platform-setting uniqueness at the right tier', function () {
    // Each rejected write runs in its own savepoint: PostgreSQL aborts the
    // surrounding RefreshDatabase transaction on a constraint error.
    /** @param Closure(): bool $write */
    $rejects = static function (Closure $write): bool {
        try {
            DB::transaction(static fn (): bool => $write());

            return false;
        } catch (QueryException) {
            return true;
        }
    };

    $folder = fn (int $tenant): array => [
        TenantColumn::TENANT_ID => $tenant,
        FolderSchema::NAME => 'Root',
        FolderSchema::PARENT_ID => null,
        FolderSchema::USER_ID => 1,
    ];

    DB::table(FolderSchema::TABLE)->insert($folder(1));
    DB::table(FolderSchema::TABLE)->insert($folder(2));

    expect($rejects(fn () => DB::table(FolderSchema::TABLE)->insert($folder(1))))->toBeTrue();

    DB::table(SettingSchema::TABLE)->insert([
        TenantColumn::TENANT_ID => null,
        SettingSchema::NAME => SettingNameEnum::WEBSITE_TITLE->value,
        SettingSchema::GROUP => SettingGroupEnum::GENERAL->value,
        SettingSchema::TYPE => SettingTypeEnum::STRING->value,
        SettingSchema::VALUE => 'one',
    ]);
    DB::table(SettingSchema::TABLE)->insert([
        TenantColumn::TENANT_ID => 1,
        SettingSchema::NAME => SettingNameEnum::WEBSITE_TITLE->value,
        SettingSchema::GROUP => SettingGroupEnum::GENERAL->value,
        SettingSchema::TYPE => SettingTypeEnum::STRING->value,
        SettingSchema::VALUE => 'store',
    ]);

    expect($rejects(fn () => DB::table(SettingSchema::TABLE)->insert([
        TenantColumn::TENANT_ID => null,
        SettingSchema::NAME => SettingNameEnum::WEBSITE_TITLE->value,
        SettingSchema::GROUP => SettingGroupEnum::GENERAL->value,
        SettingSchema::TYPE => SettingTypeEnum::STRING->value,
        SettingSchema::VALUE => 'two',
    ])))->toBeTrue();
});

it('keeps one default currency platform-wide and one default tax class per store', function () {
    // Each rejected write runs in its own savepoint: PostgreSQL aborts the
    // surrounding RefreshDatabase transaction on a constraint error.
    /** @param Closure(): bool $write */
    $rejects = static function (Closure $write): bool {
        try {
            DB::transaction(static fn (): bool => $write());

            return false;
        } catch (QueryException) {
            return true;
        }
    };

    $currency = fn (string $code, bool $default): array => [
        CurrencySchema::CODE => $code,
        CurrencySchema::NAME => $code,
        CurrencySchema::IS_DEFAULT => $default,
    ];

    DB::table(CurrencySchema::TABLE)->insert($currency('USD', true));

    expect($rejects(fn () => DB::table(CurrencySchema::TABLE)->insert($currency('EUR', true))))->toBeTrue();

    $taxClass = fn (int $tenant, string $name): array => [
        TenantColumn::TENANT_ID => $tenant,
        TaxClassSchema::NAME => $name,
        TaxClassSchema::IS_DEFAULT => true,
        TaxClassSchema::IS_SHIPPING => false,
    ];

    DB::table(TaxClassSchema::TABLE)->insert($taxClass(1, 'Standard'));
    DB::table(TaxClassSchema::TABLE)->insert($taxClass(2, 'Standard'));

    expect($rejects(fn () => DB::table(TaxClassSchema::TABLE)->insert($taxClass(1, 'Second'))))->toBeTrue();
});

it('keeps at most one verified primary domain per tenant', function () {
    foreach ([1, 2] as $tenantId) {
        DB::table(TenantSchema::TABLE)->insert([
            PrimaryKeyColumn::ID => $tenantId,
            TenantSchema::NAME => 'Store '.$tenantId,
            TenantSchema::SLUG => 'store-'.$tenantId,
        ]);
    }

    // Each rejected write runs in its own savepoint: PostgreSQL aborts the
    // surrounding RefreshDatabase transaction on a constraint error.
    /** @param Closure(): bool $write */
    $rejects = static function (Closure $write): bool {
        try {
            DB::transaction(static fn (): bool => $write());

            return false;
        } catch (QueryException) {
            return true;
        }
    };

    $domain = fn (string $host): array => [
        TenantColumn::TENANT_ID => 1,
        TenantDomainSchema::DOMAIN => $host,
        TenantDomainSchema::STATUS => TenantDomainSchema::STATUS_VERIFIED,
        TenantDomainSchema::IS_PRIMARY => true,
    ];

    DB::table(TenantDomainSchema::TABLE)->insert($domain('shop-one.test'));

    expect($rejects(fn () => DB::table(TenantDomainSchema::TABLE)->insert($domain('shop-two.test'))))->toBeTrue();

    expect(DB::table(TenantDomainSchema::TABLE)->insert([
        TenantColumn::TENANT_ID => 2,
        TenantDomainSchema::DOMAIN => 'shop-two.test',
        TenantDomainSchema::STATUS => TenantDomainSchema::STATUS_VERIFIED,
        TenantDomainSchema::IS_PRIMARY => true,
    ]))->toBeTrue();

    // The test harness defaults to tenant 1; leave it as it was found.
    expect(app(TenantContext::class)->tenantId())->toBe(1);
});

it('lets two stores use the same idempotency key but not one store twice', function () {
    // Each rejected write runs in its own savepoint: PostgreSQL aborts the
    // surrounding RefreshDatabase transaction on a constraint error.
    /** @param Closure(): bool $write */
    $rejects = static function (Closure $write): bool {
        try {
            DB::transaction(static fn (): bool => $write());

            return false;
        } catch (QueryException) {
            return true;
        }
    };

    $claim = fn (int $tenant): array => [
        TenantColumn::TENANT_ID => $tenant,
        IdempotencyKeysSchema::USER_ID => 7,
        IdempotencyKeysSchema::PRINCIPAL_KEY => 'user:7',
        IdempotencyKeysSchema::KEY => 'same-request-key',
        IdempotencyKeysSchema::STATUS => IdempotencyKeysSchema::STATUS_PROCESSING,
        IdempotencyKeysSchema::CLAIM_TOKEN => 'claim-'.$tenant,
        IdempotencyKeysSchema::RESPONSE => null,
        IdempotencyKeysSchema::EXPIRES_AT => null,
        IdempotencyKeysSchema::CREATED_AT => now(),
    ];

    DB::table(IdempotencyKeysSchema::TABLE)->insert($claim(1));
    DB::table(IdempotencyKeysSchema::TABLE)->insert($claim(2));

    expect(DB::table(IdempotencyKeysSchema::TABLE)->count())->toBe(2)
        ->and($rejects(fn () => DB::table(IdempotencyKeysSchema::TABLE)->insert($claim(1))))->toBeTrue();
});

it('scopes store-owned natural keys to one store — sequences, credentials, remaps', function () {
    // Each rejected write runs in its own savepoint: PostgreSQL aborts the
    // surrounding RefreshDatabase transaction on a constraint error.
    /** @param Closure(): bool $write */
    $rejects = static function (Closure $write): bool {
        try {
            DB::transaction(static fn (): bool => $write());

            return false;
        } catch (QueryException) {
            return true;
        }
    };

    // Document sequences: each store allocates the first (type, year) of
    // the year independently (review: the allocation lock is per store).
    $sequence = fn (int $tenant): array => [
        TenantColumn::TENANT_ID => $tenant,
        DocumentSequenceSchema::TYPE => 'invoice',
        DocumentSequenceSchema::YEAR => 2026,
    ];

    DB::table(DocumentSequenceSchema::TABLE)->insert($sequence(1));
    DB::table(DocumentSequenceSchema::TABLE)->insert($sequence(2));

    expect($rejects(fn () => DB::table(DocumentSequenceSchema::TABLE)->insert($sequence(1))))->toBeTrue();

    // Payment driver credentials: the second store can configure the same
    // driver key (A2 — per-tenant credentials from day one).
    $credential = fn (int $tenant, string $value): array => [
        TenantColumn::TENANT_ID => $tenant,
        PaymentDriverCredentialSchema::DRIVER => 'stripe',
        PaymentDriverCredentialSchema::KEY => 'secret_key',
        PaymentDriverCredentialSchema::VALUE => $value,
    ];

    DB::table(PaymentDriverCredentialSchema::TABLE)->insert($credential(1, 'sk_a'));
    DB::table(PaymentDriverCredentialSchema::TABLE)->insert($credential(2, 'sk_b'));

    expect($rejects(fn () => DB::table(PaymentDriverCredentialSchema::TABLE)->insert($credential(1, 'duplicate'))))->toBeTrue();

    // Transfer remaps: the destination store leads the idempotency key, so
    // two stores importing the same source artifact converge separately.
    $remap = fn (int $tenant): array => [
        TenantColumn::TENANT_ID => $tenant,
        TransferRemapSchema::SOURCE_INSTALLATION => '3f2a1b0c-0000-4000-8000-000000000001',
        TransferRemapSchema::MODULE => 'catalog',
        TransferRemapSchema::ENTITY => 'product',
        TransferRemapSchema::SOURCE_ID => 42,
        TransferRemapSchema::LOCAL_ID => 900 + $tenant,
    ];

    DB::table(TransferRemapSchema::TABLE)->insert($remap(1));
    DB::table(TransferRemapSchema::TABLE)->insert($remap(2));

    expect($rejects(fn () => DB::table(TransferRemapSchema::TABLE)->insert($remap(1))))->toBeTrue();
});
