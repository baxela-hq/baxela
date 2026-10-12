<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditLog;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Audit\Tests\Feature\HelperTrait;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Contact\Models\ContactMessage;
use Modules\Contact\Schemas\ContactMessage\ContactMessageStatusEnum;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function auditLogRow(array $overrides = []): array
{
    return array_merge([
        AuditLogSchema::MODULE => 'menu',
        AuditLogSchema::ENTITY => 'menu',
        AuditLogSchema::ACTION => 'updated',
        AuditLogSchema::ENTITY_ID => 1,
        AuditLogSchema::PROPERTIES => ['id' => 1, 'location' => 'header'],
        AuditLogSchema::IP_ADDRESS => '10.0.0.1',
        AuditLogSchema::USER_AGENT => 'test-agent',
    ], $overrides);
}

it('records an admin mutation with actor, entity and request context', function () {
    $admin = $this->superAdminUser();
    $this->actingAs($admin);

    $message = ContactMessage::factory()->create(['status' => ContactMessageStatusEnum::UNREAD]);

    $this->patchJson('api/v1/contact/admin/messages/'.$message->id.'/status', ['status' => 'replied'])
        ->assertOk();

    $log = AuditLog::query()->where(AuditLogSchema::MODULE, 'contact')->first();

    expect($log)->not->toBeNull()
        ->and($log->{AuditLogSchema::USER_ID})->toBe($admin->id)
        ->and($log->{AuditLogSchema::ENTITY})->toBe('contact_message')
        ->and($log->{AuditLogSchema::ACTION})->toBe('status_updated')
        ->and($log->{AuditLogSchema::ENTITY_ID})->toBe($message->id)
        ->and($log->{AuditLogSchema::PROPERTIES}['status'])->toBe('replied')
        ->and($log->{AuditLogSchema::IP_ADDRESS})->not->toBeNull();
});

it('lists audit logs with filters and sorting for permitted users', function () {
    $this->actingAs($this->superAdminUser());

    $other = User::factory()->active()->create();
    AuditLog::query()->create(auditLogRow([
        AuditLogSchema::USER_ID => $other->id,
        AuditLogSchema::MODULE => 'catalog',
        AuditLogSchema::ENTITY => 'product',
        AuditLogSchema::ACTION => 'created',
    ]));
    AuditLog::query()->create(auditLogRow());

    $list = $this->getJson($this->baseUrl('/admin/logs'));
    $list->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data', 'meta']);

    $this->getJson($this->baseUrl('/admin/logs?filter[module]=catalog'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.module', 'catalog');

    $this->getJson($this->baseUrl('/admin/logs?filter[user_id]='.$other->id))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson($this->baseUrl('/admin/logs?filter[action]=updated&sort=-id'))
        ->assertOk()
        ->assertJsonPath('data.0.action', 'updated');

    $today = now()->toDateString();
    $this->getJson($this->baseUrl("/admin/logs?filter[created_from]={$today}&filter[created_to]={$today}"))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('shows a single audit log with its acting user', function () {
    $admin = $this->superAdminUser();
    $this->actingAs($admin);

    $row = AuditLog::query()->create(auditLogRow([
        AuditLogSchema::USER_ID => $admin->id,
    ]));

    $this->getJson($this->baseUrl('/admin/logs/'.$row->id))
        ->assertOk()
        ->assertJsonPath('data.id', $row->id)
        ->assertJsonPath('data.user.id', $admin->id)
        ->assertJsonPath('data.user.email', $admin->email);
});

it('rejects the audit endpoints without the audit permission', function () {
    $this->actingAs(User::factory()->active()->create());

    $this->getJson($this->baseUrl('/admin/logs'))->assertForbidden();

    $this->getJson($this->baseUrl('/admin/logs/1'))->assertForbidden();
});

it('allows non-super-admin users holding the audit permission', function () {
    $user = User::factory()->active()->create();
    $user->givePermissionTo(
        Permission::query()->firstOrCreate([
            'name' => 'audit.admin.logs.list',
            'guard_name' => GuardsEnum::WEB->value,
        ])
    );
    $this->actingAs($user);

    AuditLog::query()->create(auditLogRow());

    $this->getJson($this->baseUrl('/admin/logs'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('exposes the account permissions payload for the admin UI', function () {
    $admin = $this->superAdminUser();
    $this->actingAs($admin);

    $this->getJson('api/v1/auth/admin/account')
        ->assertOk()
        ->assertJsonPath('data.permissions.0', '*');

    $user = User::factory()->active()->create();
    foreach (['audit.admin.logs.list', 'auth.admin.account.show'] as $name) {
        $user->givePermissionTo(
            Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => GuardsEnum::WEB->value,
            ])
        );
    }
    $this->actingAs($user);

    // the account route is itself permission-gated, so both are granted and
    // only membership of the audit permission is asserted
    $this->getJson('api/v1/auth/admin/account')
        ->assertOk()
        ->assertJsonPath('data.permissions', ['audit.admin.logs.list', 'auth.admin.account.show']);
});
