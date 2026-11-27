<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;
use Modules\Core\Exceptions\TenantContextMissingException;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Core\Support\Tenancy\TenantWriteStamp;

uses(RefreshDatabase::class);

it('stamps a raw store-owned insert with the active tenant', function () {
    $context = app(TenantContext::class);

    expect($context->runForTenant(77, fn () => TenantWriteStamp::tenantIdForWrite()))->toBe(77)
        ->and(TenantWriteStamp::column())->toBe(TenantColumn::TENANT_ID);

    $context->runForTenant(77, function (): void {
        $payload = [
            ContactMessageSchema::NAME => 'Visitor',
            ContactMessageSchema::EMAIL => 'visitor@example.test',
            ContactMessageSchema::SUBJECT => 'Stamped message',
            ContactMessageSchema::CONTENT => 'Body',
            ContactMessageSchema::CREATED_AT => now(),
            ContactMessageSchema::UPDATED_AT => now(),
        ];

        $rejected = false;

        try {
            DB::transaction(fn () => DB::table(ContactMessageSchema::TABLE)->insert($payload));
        } catch (QueryException) {
            $rejected = true;
        }

        DB::table(ContactMessageSchema::TABLE)->insert(
            $payload + [TenantColumn::TENANT_ID => TenantWriteStamp::tenantIdForWrite()],
        );

        expect($rejected)->toBeTrue()
            ->and((int) DB::table(ContactMessageSchema::TABLE)
                ->where(ContactMessageSchema::SUBJECT, 'Stamped message')
                ->value(TenantColumn::TENANT_ID))
            ->toBe(77);
    });
});

it('refuses to hand out a tenant id outside tenant mode', function () {
    $context = app(TenantContext::class);
    $context->reset();

    expect(fn () => TenantWriteStamp::tenantIdForWrite())->toThrow(TenantContextMissingException::class)
        ->and(fn () => $context->runCentral('tenant.probe', fn () => TenantWriteStamp::tenantIdForWrite()))
        ->toThrow(TenantContextMissingException::class);
});
