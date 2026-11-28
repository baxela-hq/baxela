<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Audit\Models\AuditLog;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Tenant\Actions\Admin\Tenant\CreateTenantAction;
use Modules\Tenant\Exceptions\Tenant\SlugTakenException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('lets the platform super-admin create, show, update, suspend, reactivate and delete a store', function () {
    $this->actingAs($this->superAdminUser(), 'sanctum');

    $created = $this->postJson($this->baseUrl('/admin/tenants'), [
        'name' => 'Acme Store',
        'email' => 'acme@example.test',
        'slug' => 'acme',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.slug', 'acme')
        ->assertJsonPath('data.status', 'active');

    $id = (string) $created->json('data.id');

    $this->getJson($this->baseUrl('/admin/tenants'))->assertOk()->assertJsonPath('data.0.id', (int) $id);
    $this->getJson($this->baseUrl('/admin/tenants/'.$id))->assertOk()->assertJsonPath('data.name', 'Acme Store');

    // The slug is the store's identity: never changed by an update.
    $this->patchJson($this->baseUrl('/admin/tenants/'.$id), [
        'name' => 'Acme Renamed',
        'email' => 'acme@example.test',
        'slug' => 'renamed',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme Renamed')
        ->assertJsonPath('data.slug', 'acme');

    $this->postJson($this->baseUrl('/admin/tenants/'.$id.'/suspend'))->assertOk()->assertJsonPath('data.status', 'suspended');
    $this->postJson($this->baseUrl('/admin/tenants/'.$id.'/activate'))->assertOk()->assertJsonPath('data.status', 'active');

    $this->deleteJson($this->baseUrl('/admin/tenants/'.$id))->assertNoContent();

    // Soft deletion: gone from every normal read, nothing erased.
    expect(Tenant::query()->count())->toBe(0)
        ->and(Tenant::withTrashed()->count())->toBe(1)
        ->and(Tenant::withTrashed()->first()->{TenantSchema::SLUG})->toBe('acme');

    // Every platform write ran as a central operation and left its record.
    $outcomes = AuditLog::query()
        ->where(AuditLogSchema::ENTITY, 'central_operation_run')
        ->pluck(AuditLogSchema::PROPERTIES)
        ->map(fn ($properties) => $properties['operation_id'])
        ->all();

    expect($outcomes)->toBe([
        'tenant.create',
        'tenant.update',
        'tenant.set-status',
        'tenant.set-status',
        'tenant.delete',
    ]);
});

it('refuses a taken slug with a conflict, not a server error', function () {
    $this->actingAs($this->superAdminUser(), 'sanctum');

    $this->postJson($this->baseUrl('/admin/tenants'), ['name' => 'Acme', 'email' => 'acme@example.test', 'slug' => 'acme'])->assertStatus(201);

    // Validation answers first for an ordinary duplicate…
    $this->postJson($this->baseUrl('/admin/tenants'), ['name' => 'Acme 2', 'email' => 'acme2@example.test', 'slug' => 'acme'])
        ->assertStatus(422);

    // …and the operation itself resolves the race validation cannot close —
    // two requests that both passed it — on the unique index, with a
    // conflict rather than a server error.
    $this->assertThrowsCode(
        fn () => app(CreateTenantAction::class)->handle([
            'name' => 'Acme 3',
            'email' => 'acme3@example.test',
            'slug' => 'acme',
        ]),
        SlugTakenException::class,
        'tenant.tenant.slug_taken',
        httpStatus: 409,
    );

    expect(Tenant::query()->count())->toBe(1);
});

it('keeps every tenant route to the platform super-admin', function () {
    $staff = $this->plainUser();
    $acme = $this->createTenant('acme');
    $id = (string) $acme->getKey();

    $store = ['name' => 'X', 'email' => 'x@example.test', 'slug' => 'x'];

    $routes = [
        ['get', $this->baseUrl('/admin/tenants'), null],
        ['post', $this->baseUrl('/admin/tenants'), $store],
        ['get', $this->baseUrl('/admin/tenants/'.$id), null],
        ['patch', $this->baseUrl('/admin/tenants/'.$id), ['name' => 'X', 'email' => 'x@example.test', 'slug' => null]],
        ['post', $this->baseUrl('/admin/tenants/'.$id.'/suspend'), null],
        ['post', $this->baseUrl('/admin/tenants/'.$id.'/activate'), null],
        ['delete', $this->baseUrl('/admin/tenants/'.$id), null],
        ['get', $this->baseUrl('/admin/tenants/'.$id.'/domains'), null],
        ['post', $this->baseUrl('/admin/tenants/'.$id.'/domains'), ['domain' => 'acme.test']],
    ];

    foreach ($routes as [$method, $uri, $payload]) {
        // §3.2: the store is earned before the permission check, so a
        // non-member staff account answers with the membership boundary.
        $this->actingAs($staff, 'sanctum')->json($method, $uri, $payload ?? [])
            ->assertStatus(403)
            ->assertJsonPath('code', 'core.tenant.membership_required');
    }

    // Refused means refused: nothing was written, moved or removed.
    expect(Tenant::query()->count())->toBe(1)
        ->and($acme->fresh()->{TenantSchema::NAME})->toBe('Acme')
        ->and($acme->domains()->count())->toBe(0)
        ->and(AuditLog::query()->where(AuditLogSchema::ENTITY, 'central_operation_run')->count())->toBe(0);
});

it('never gives a store an address that cannot name a subdomain', function () {
    $this->actingAs($this->superAdminUser(), 'sanctum');

    foreach (['Not A Slug', 'acme_store', '-acme', str_repeat('a', 64)] as $slug) {
        $this->postJson($this->baseUrl('/admin/tenants'), ['name' => 'Acme', 'email' => 'acme@example.test', 'slug' => $slug])
            ->assertStatus(422);
    }

    expect(Str::length($slug))->toBe(64)
        ->and(Tenant::query()->count())->toBe(0);
});
