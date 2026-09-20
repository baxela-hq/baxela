<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Notification\Models\PushSubscription;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\PushSubscription\PushSubscriptionSchema;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function pushSubscriptionPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/dQw4w9WgXcQ'): array
{
    return [
        PushSubscriptionSchema::ENDPOINT => $endpoint,
        PushSubscriptionSchema::KEYS => [
            PushSubscriptionSchema::P256DH => 'BIP-12-...',
            PushSubscriptionSchema::AUTH => 'auth-secret',
        ],
        PushSubscriptionSchema::USER_AGENT => 'Mozilla/5.0',
        PushSubscriptionSchema::LOCALE => 'en',
    ];
}

it('exposes the vapid public key', function () {
    config(['notification.notifications.webpush.vapid.public_key' => 'test-public-key']);

    $this->getJson($this->baseUrl('/webpush/vapid-public-key'))
        ->assertOk()
        ->assertJsonPath('data.public_key', 'test-public-key');
});

it('signals web push as unavailable when vapid keys are unset', function () {
    config(['notification.notifications.webpush.vapid.public_key' => null]);

    $this->getJson($this->baseUrl('/webpush/vapid-public-key'))
        ->assertOk()
        ->assertJsonPath('data.public_key', null);
});

it('upserts a push subscription for the signed-in user', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson($this->baseUrl('/user/push-subscriptions'), pushSubscriptionPayload())
        ->assertCreated()
        ->assertJsonPath('data.endpoint', pushSubscriptionPayload()[PushSubscriptionSchema::ENDPOINT]);

    // Re-registering the same browser refreshes the row instead of piling
    // up duplicates.
    $this->actingAs($user, 'sanctum')
        ->postJson($this->baseUrl('/user/push-subscriptions'), pushSubscriptionPayload())
        ->assertOk();

    expect(PushSubscription::query()->count())->toBe(1)
        ->and(PushSubscription::query()->first()->{PushSubscriptionSchema::P256DH})->toBe('BIP-12-...');
});

it('re-assigns a browser subscription on login switch', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($first, 'sanctum')
        ->postJson($this->baseUrl('/user/push-subscriptions'), pushSubscriptionPayload())
        ->assertCreated();

    $this->actingAs($second, 'sanctum')
        ->postJson($this->baseUrl('/user/push-subscriptions'), pushSubscriptionPayload())
        ->assertOk();

    expect(PushSubscription::query()->count())->toBe(1)
        ->and(PushSubscription::query()->first()->{PushSubscriptionSchema::USER_ID})->toBe($second->id);
});

it('deletes only the callers own subscription by endpoint', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $subscription = PushSubscription::query()->create([
        PushSubscriptionSchema::USER_ID => $user->id,
        PushSubscriptionSchema::ENDPOINT => pushSubscriptionPayload()[PushSubscriptionSchema::ENDPOINT],
    ]);
    PushSubscription::query()->create([
        PushSubscriptionSchema::USER_ID => $other->id,
        PushSubscriptionSchema::ENDPOINT => 'https://other.example/push/xyz',
    ]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson($this->baseUrl('/user/push-subscriptions'), [
            PushSubscriptionSchema::ENDPOINT => $subscription->{PushSubscriptionSchema::ENDPOINT},
        ])
        ->assertNoContent();

    expect(PushSubscription::query()->count())->toBe(1)
        ->and(PushSubscription::query()->first()->{PushSubscriptionSchema::USER_ID})->toBe($other->id);
});

it('lists admin push subscriptions behind the staff gate', function () {
    $admin = $this->superAdminUser();
    $this->actingAs($admin, 'sanctum')
        ->postJson($this->baseUrl('/admin/push-subscriptions'), pushSubscriptionPayload())
        ->assertCreated();

    $this->actingAs($admin, 'sanctum')
        ->getJson($this->baseUrl('/admin/push-subscriptions'))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $customer = User::factory()->create();
    $this->actingAs($customer, 'sanctum')
        ->getJson($this->baseUrl('/admin/push-subscriptions'))
        ->assertForbidden();
});

it('no-ops the webpush channel when vapid keys are not configured', function () {
    config([
        'notification.notifications.webpush.vapid.public_key' => null,
        'notification.notifications.webpush.vapid.private_key' => null,
    ]);

    $user = User::factory()->create();
    PushSubscription::query()->create([
        PushSubscriptionSchema::USER_ID => $user->id,
        PushSubscriptionSchema::ENDPOINT => pushSubscriptionPayload()[PushSubscriptionSchema::ENDPOINT],
    ]);

    app(NotificationDispatcherInterface::class)->dispatch(new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: 'user',
        recipients: ['webpush' => [$user->id]],
        data: ['database' => ['order_code' => 'ORD-3003', 'amount' => '20.00']],
        channel: ['webpush'],
    ));

    // The subscription survives — without VAPID keys nothing is sent, and
    // nothing is treated as expired.
    expect(PushSubscription::query()->count())->toBe(1);
});
