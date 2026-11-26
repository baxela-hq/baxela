<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Exceptions\TenantContextMissingException;
use Modules\Core\Exceptions\TenantOwnershipException;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Models\TenantCurrency;
use Modules\Tenant\Schemas\TenantCurrency\TenantCurrencySchema;
use Modules\Tenant\Tests\Feature\HelperTrait;
use Modules\Tenant\Tests\Fixtures\PlatformTierProbe;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('starts inactive and reports no tenant outside tenant mode', function () {
    $context = app(TenantContext::class);

    expect($context->mode())->toBe(TenantContext::MODE_INACTIVE)
        ->and($context->tenantId())->toBeNull()
        ->and($context->tenant())->toBeNull()
        ->and($context->centralOperation())->toBeNull()
        ->and($context->cachePrefix())->toBe('');
});

it('throws instead of querying unscoped when the context is missing', function () {
    expect(fn () => TenantCurrency::query()->count())->toThrow(TenantContextMissingException::class)
        ->and(fn () => TenantCurrency::query()->exists())->toThrow(TenantContextMissingException::class)
        ->and(fn () => TenantCurrency::query()->find(1))->toThrow(TenantContextMissingException::class)
        ->and(fn () => TenantCurrency::query()->create([TenantCurrencySchema::CURRENCY_ID => 1]))
        ->toThrow(TenantContextMissingException::class);
});

it('loads the snapshot of an existing tenant', function () {
    $tenant = $this->createTenant('acme');
    $context = app(TenantContext::class);

    $context->forTenant((int) $tenant->getKey());

    expect($context->tenant()?->slug)->toBe('acme')
        ->and($context->cachePrefix())->toBe('t'.$tenant->getKey().':');
});

it('filters reads and stamps writes by the request tenant', function () {
    $acme = $this->createTenant('acme');
    $other = $this->createTenant('other');
    $context = app(TenantContext::class);

    $context->forTenant((int) $acme->getKey());
    TenantCurrency::query()->create([TenantCurrencySchema::CURRENCY_ID => 1]);

    $context->forTenant((int) $other->getKey());
    TenantCurrency::query()->create([TenantCurrencySchema::CURRENCY_ID => 1]);
    TenantCurrency::query()->create([TenantCurrencySchema::CURRENCY_ID => 2]);

    $context->forTenant((int) $acme->getKey());

    expect(TenantCurrency::query()->count())->toBe(1)
        ->and(TenantCurrency::query()->pluck(TenantColumn::TENANT_ID)->all())->toBe([(int) $acme->getKey()]);

    $context->forTenant((int) $other->getKey());

    expect(TenantCurrency::query()->count())->toBe(2);
});

it('refuses to write a row into another store, and to move one', function () {
    $acme = $this->createTenant('acme');
    $other = $this->createTenant('other');
    $context = app(TenantContext::class);

    $context->forTenant((int) $acme->getKey());

    // `tenant_id` is not fillable: mass assignment drops it, and the row is stamped with the request's tenant.
    /** @var TenantCurrency $ignored */
    $ignored = TenantCurrency::query()->create([
        TenantCurrencySchema::CURRENCY_ID => 1,
        TenantColumn::TENANT_ID => $other->getKey(),
    ]);

    expect((int) $ignored->getAttribute(TenantColumn::TENANT_ID))->toBe((int) $acme->getKey());

    // Assigned directly, another store's id is refused — nothing is written.
    $foreign = new TenantCurrency([TenantCurrencySchema::CURRENCY_ID => 2]);
    $foreign->{TenantColumn::TENANT_ID} = $other->getKey();

    expect(fn () => $foreign->save())->toThrow(TenantOwnershipException::class)
        ->and(TenantCurrency::query()->count())->toBe(1);

    // Moving an existing row is refused too.
    $row = TenantCurrency::query()->create([TenantCurrencySchema::CURRENCY_ID => 3]);

    $row->{TenantColumn::TENANT_ID} = $other->getKey();

    expect(fn () => $row->save())->toThrow(TenantOwnershipException::class)
        ->and((int) $row->fresh()->{TenantColumn::TENANT_ID})->toBe((int) $acme->getKey());
});

it('reads across stores only in central mode', function () {
    $acme = $this->createTenant('acme');
    $other = $this->createTenant('other');
    $context = app(TenantContext::class);

    foreach ([[$acme, 1], [$other, 1], [$other, 2]] as [$tenant, $currencyId]) {
        $context->forTenant((int) $tenant->getKey());
        TenantCurrency::query()->create([TenantCurrencySchema::CURRENCY_ID => $currencyId]);
    }

    $context->forTenant((int) $acme->getKey());

    expect($context->runCentral('tenant.probe', fn () => TenantCurrency::query()->count()))->toBe(3)
        // The enclosing request's tenant is exactly what remains.
        ->and($context->mode())->toBe(TenantContext::MODE_TENANT)
        ->and($context->tenantId())->toBe((int) $acme->getKey())
        ->and($context->centralOperation())->toBeNull();
});

it('restores the exact previous state, on return and on exception, through nested runs', function () {
    $acme = $this->createTenant('acme');
    $other = $this->createTenant('other');
    $context = app(TenantContext::class);

    $context->forTenant((int) $acme->getKey());

    $inside = $context->runForTenant(
        (int) $other->getKey(),
        fn () => $context->runCentral('tenant.probe', fn () => $context->centralOperation()),
    );

    expect($inside)->toBe('tenant.probe')
        ->and($context->mode())->toBe(TenantContext::MODE_TENANT)
        ->and($context->tenantId())->toBe((int) $acme->getKey());

    try {
        $context->runForTenant((int) $other->getKey(), function (): void {
            throw new RuntimeException('boom');
        });
        $this->fail('The nested run should have thrown.');
    } catch (RuntimeException) {
        // the point is the state after the exception
    }

    expect($context->tenantId())->toBe((int) $acme->getKey());

    $context->reset();

    expect($context->mode())->toBe(TenantContext::MODE_INACTIVE);
});

it('lets a central operation write a platform-tier row, and nothing else without a tenant', function () {
    Schema::create('probe_platform_rows', function ($table): void {
        $table->id();
        $table->unsignedBigInteger(TenantColumn::TENANT_ID)->nullable();
    });

    $context = app(TenantContext::class);

    $row = $context->runCentral('tenant.probe', fn () => PlatformTierProbe::query()->create());

    expect($row->getAttribute(TenantColumn::TENANT_ID))->toBeNull();

    $context->deactivate();

    expect(fn () => $context->runCentral(
        'tenant.probe',
        fn () => TenantCurrency::query()->create([TenantCurrencySchema::CURRENCY_ID => 1]),
    ))->toThrow(TenantContextMissingException::class);
});

it('restores, in order, each state a job attempt pushed', function () {
    $acme = $this->createTenant('acme');
    $other = $this->createTenant('other');
    $context = app(TenantContext::class);

    $context->pushSnapshot();
    $context->forTenant((int) $acme->getKey());
    $context->pushSnapshot();
    $context->forTenant((int) $other->getKey());

    expect($context->tenantId())->toBe((int) $other->getKey());

    $context->popSnapshot();

    expect($context->tenantId())->toBe((int) $acme->getKey());

    $context->popSnapshot();

    expect($context->mode())->toBe(TenantContext::MODE_INACTIVE);

    // Nothing left to pop is a no-op, never an error.
    $context->popSnapshot();

    expect($context->mode())->toBe(TenantContext::MODE_INACTIVE);
});
