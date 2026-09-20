<?php

use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Events\Order\OrderCreatedEvent;
use Modules\Notification\Events\NotificationCreated;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationSchema;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Support\BroadcastChannels;
use Modules\Notification\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function dispatchDatabaseNotification(int $recipientId): void
{
    app(NotificationDispatcherInterface::class)->dispatch(new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: 'user',
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

    Queue::assertPushed(BroadcastEvent::class, function (BroadcastEvent $job) use ($user): bool {
        $event = $job->event;

        if (! $event instanceof NotificationCreated) {
            return false;
        }

        $channels = $event->broadcastOn();
        $payload = $event->broadcastWith();

        return $event->notifiableId === $user->id
            && $event->broadcastAs() === 'notification.created'
            && count($channels) === 1
            && $channels[0]->name === "private-user.{$user->id}"
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

it('authorizes the private channel only for its owner', function () {
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

    $owner = User::factory()->create();
    $other = User::factory()->create();

    $this->postJson($this->baseUrl('/broadcasting/auth'), [
        'channel_name' => "private-user.{$owner->id}",
    ])->assertUnauthorized();

    $this->actingAs($owner, 'sanctum')
        ->postJson($this->baseUrl('/broadcasting/auth'), [
            'channel_name' => "private-user.{$owner->id}",
            'socket_id' => '1.1',
        ])
        ->assertOk();

    $this->actingAs($other, 'sanctum')
        ->postJson($this->baseUrl('/broadcasting/auth'), [
            'channel_name' => "private-user.{$owner->id}",
            'socket_id' => '1.1',
        ])
        ->assertForbidden();
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
