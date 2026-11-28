<?php

use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Events\Order\OrderCreatedEvent;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Notification\Events\NotificationCreated;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationSchema;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Support\BroadcastChannels;
use Modules\Notification\Tests\Feature\HelperTrait;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Schemas\Tenant\TenantSchema;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function dispatchDatabaseNotification(int $recipientId, string $audience = 'user'): void
{
    app(NotificationDispatcherInterface::class)->dispatch(new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: $audience,
        recipients: ['database' => [$recipientId]],
        data: ['database' => ['order_code' => 'ORD-1001', 'amount' => '120.00']],
        channel: ['database'],
        meta: ['order_code' => 'ORD-1001'],
    ));
}

it('queues a broadcast for every created notification on the recipient private channel', function () {
    Queue::fake();

    $user = User::factory()->create();

    dispatchDatabaseNotification($user->id);

    $tenantId = (int) app(TenantContext::class)->tenantId();

    Queue::assertPushed(BroadcastEvent::class, function (BroadcastEvent $job) use ($user, $tenantId): bool {
        $event = $job->event;

        if (! $event instanceof NotificationCreated) {
            return false;
        }

        $channels = $event->broadcastOn();
        $payload = $event->broadcastWith();

        return $event->tenantId === $tenantId
            && $event->notifiableId === $user->id
            && $event->audience === 'user'
            && $event->broadcastAs() === 'notification.created'
            && count($channels) === 1
            && $channels[0]->name === "private-tenant.{$tenantId}.notification.user.{$user->id}"
            && $payload['code'] === NotificationCodeEnum::ORDER_ORDER_CREATED->value
            && $payload['meta'] === ['order_code' => 'ORD-1001']
            && $payload['read_at'] === null
            && $payload['unread_count'] === 1;
    });
    Queue::assertPushed(BroadcastEvent::class, 1);
});

it('counts the unread total including the new row in the broadcast payload', function () {
    Queue::fake();

    $user = User::factory()->create();

    Notification::query()->create([
        NotificationSchema::USER_ID => $user->id,
        NotificationSchema::CODE => NotificationCodeEnum::ORDER_ORDER_COMPLETED->value,
        NotificationSchema::AUDIENCE => 'user',
        NotificationSchema::TITLE => 'Order completed',
        NotificationSchema::BODY => 'Order ORD-1000 has been delivered.',
    ]);

    dispatchDatabaseNotification($user->id);

    Queue::assertPushed(BroadcastEvent::class, function (BroadcastEvent $job): bool {
        return $job->event instanceof NotificationCreated
            && $job->event->broadcastWith()['unread_count'] === 2;
    });
});

it('scopes the broadcast unread count and channel to the notification audience', function () {
    Queue::fake();

    $user = User::factory()->create();

    // Staff who shop hold rows in both audiences under one user id;
    // each app's badge must count only its own audience (the unscoped
    // count made the admin bell disagree with the admin inbox).
    foreach (['admin', 'user'] as $audience) {
        Notification::query()->create([
            NotificationSchema::USER_ID => $user->id,
            NotificationSchema::CODE => NotificationCodeEnum::ORDER_ORDER_COMPLETED->value,
            NotificationSchema::AUDIENCE => $audience,
            NotificationSchema::TITLE => "Order completed ({$audience})",
            NotificationSchema::BODY => 'Order ORD-1000 has been delivered.',
        ]);
    }

    dispatchDatabaseNotification($user->id, 'admin');

    $tenantId = (int) app(TenantContext::class)->tenantId();

    Queue::assertPushed(BroadcastEvent::class, function (BroadcastEvent $job) use ($user, $tenantId): bool {
        $event = $job->event;

        return $event instanceof NotificationCreated
            && $event->tenantId === $tenantId
            && $event->audience === 'admin'
            && $event->broadcastOn()[0]->name === "private-tenant.{$tenantId}.notification.admin.{$user->id}"
            && $event->broadcastWith()['unread_count'] === 2;
    });
});

it('authorizes tenant-scoped private channels and denies cross-tenant subscriptions', function () {
    // The suite's null broadcaster accepts every channel; re-bind the
    // module channels to a real pusher-compatible connection so the
    // closure is actually consulted.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http'],
        ],
    ]);
    $this->app->forgetInstance(BroadcasterContract::class);
    BroadcastChannels::register();

    $owner = User::factory()->active()->create();
    $this->withDefaultStoreMembership($owner);
    $tenantId = (int) app(TenantContext::class)->tenantId();

    $other = User::factory()->active()->create();
    $this->withDefaultStoreMembership($other);

    $foreign = Tenant::query()->create([
        TenantSchema::NAME => 'Foreign Store',
        TenantSchema::SLUG => 'foreign-store',
        TenantSchema::EMAIL => 'foreign@example.test',
        TenantSchema::STATUS => TenantSchema::STATUS_ACTIVE,
    ]);

    $userChannel = "private-tenant.{$tenantId}.notification.user.{$owner->id}";
    $foreignChannel = "private-tenant.{$foreign->getKey()}.notification.user.{$owner->id}";
    $adminChannel = "private-tenant.{$tenantId}.notification.admin.{$owner->id}";

    $this->postJson($this->baseUrl('/broadcasting/auth'), [
        'channel_name' => $userChannel,
    ])->assertUnauthorized();

    $this->actingAs($owner, 'sanctum')
        ->postJson($this->baseUrl('/broadcasting/auth'), [
            'channel_name' => $userChannel,
            'socket_id' => '1.1',
        ])
        ->assertOk();

    // Same user id, different store: editing the channel prefix must not
    // grant access to another store's stream (§10 row 35).
    $this->actingAs($owner, 'sanctum')
        ->postJson($this->baseUrl('/broadcasting/auth'), [
            'channel_name' => $foreignChannel,
            'socket_id' => '1.1',
        ])
        ->assertForbidden();

    $this->actingAs($other, 'sanctum')
        ->postJson($this->baseUrl('/broadcasting/auth'), [
            'channel_name' => $userChannel,
            'socket_id' => '1.1',
        ])
        ->assertForbidden();

    // A customer is not a store staff member: no admin stream.
    $this->actingAs($owner, 'sanctum')
        ->postJson($this->baseUrl('/broadcasting/auth'), [
            'channel_name' => $adminChannel,
            'socket_id' => '1.1',
        ])
        ->assertForbidden();

    // A platform role is not a store role: only this store's active
    // staff may join its admin stream.
    $admin = User::factory()->active()->create();
    $this->withDefaultStoreMembership($admin);
    $access = app(AccessGatewayInterface::class);
    $roleId = app(TenantContext::class)->runForTenant(
        $tenantId,
        fn () => $access->seedTenantRoles($tenantId),
    );
    $access->assignTenantRole($admin->id, $tenantId, $roleId);

    $this->actingAs($admin, 'sanctum')
        ->postJson($this->baseUrl('/broadcasting/auth'), [
            'channel_name' => "private-tenant.{$tenantId}.notification.admin.{$admin->id}",
            'socket_id' => '1.1',
        ])
        ->assertOk();
});

it('keeps broadcasting realtime notifications when an order is created', function () {
    // Fake only the broadcast job so the queued notification listeners
    // still run synchronously and insert the database rows.
    Queue::fake([BroadcastEvent::class]);

    $admin = $this->superAdminUser();
    $this->actingAs($admin, 'sanctum');

    $customer = User::factory()->create();

    event(OrderCreatedEvent::fill([
        'id' => 1,
        'user_id' => $customer->id,
        'order_code' => 'ORD-2002',
        'status' => 'pending',
        'total_amount' => 150.00,
        'expires_at' => now()->addHour()->toDateTimeString(),
        'created_at' => now()->toDateTimeString(),
    ]));

    Queue::assertPushed(BroadcastEvent::class, function (BroadcastEvent $job) use ($customer): bool {
        return $job->event instanceof NotificationCreated
            && $job->event->notifiableId === $customer->id
            && $job->event->broadcastWith()['code'] === NotificationCodeEnum::ORDER_ORDER_CREATED->value;
    });
});
