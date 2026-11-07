<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notification\Models\Notification;
use Modules\Notification\Models\NotificationPreference;
use Modules\Notification\Models\PushSubscription;
use Modules\Notification\Models\TelegramSubscription;
use Modules\Notification\Schemas\Notification\NotificationPreferenceSchema;
use Modules\Notification\Schemas\Notification\NotificationSchema;
use Modules\Notification\Schemas\PushSubscription\PushSubscriptionSchema;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;
use Modules\Notification\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports notification data (export-only)', function () {
    $this->actingAs($this->superAdminUser());

    Notification::query()->create([
        'user_id' => 7, 'code' => 'order.order.paid', 'audience' => 'user',
        'title' => 'Paid', 'body' => 'Order paid', 'meta' => ['order_id' => 3],
    ]);
    NotificationPreference::query()->create([
        'user_id' => 7, 'code' => 'order.order.paid', 'audience' => 'user',
        'channel' => 'email', 'is_enabled' => true,
    ]);
    PushSubscription::query()->create([
        'user_id' => 7, 'endpoint' => 'https://push.example/1', 'p256dh' => 'k', 'auth' => 'a', 'locale' => 'en',
    ]);
    TelegramSubscription::query()->create([
        'user_id' => 7, 'chat_id' => 99, 'username' => 'jane', 'linked_at' => now(),
    ]);

    $file = $this->exportedNotificationDataFile();

    expect($file['module'])->toBe('notification')
        ->and(array_column($file['sections'], 'entity'))->toBe([
            'notifications', 'notification-preferences', 'push-subscriptions', 'telegram-subscriptions',
        ])
        ->and($file['sections'][0]['rows'][0]['payload'][NotificationSchema::CODE])->toBe('order.order.paid')
        ->and($file['sections'][1]['rows'][0]['payload'][NotificationPreferenceSchema::CHANNEL])->toBe('email')
        ->and($file['sections'][2]['rows'][0]['payload'][PushSubscriptionSchema::ENDPOINT])->toBe('https://push.example/1')
        ->and($file['sections'][3]['rows'][0]['payload'][TelegramSubscriptionSchema::CHAT_ID])->toBe(99);
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
});
