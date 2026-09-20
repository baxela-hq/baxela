<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Builders\WebPushPayloadBuilder;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('renders web push from the database variables and resolves rtl direction', function () {
    $this->seed(CoreDatabaseSeeder::class);

    $builder = app(WebPushPayloadBuilder::class);

    $message = new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: 'user',
        recipients: ['database' => [42]],
        data: ['database' => ['order_code' => 'ORD-3003', 'amount' => '20.00']],
        locale: 'fa',
        meta: ['order_code' => 'ORD-3003'],
    );

    $webPush = $builder->build($message);

    expect($webPush->title)->toBe('سفارش ORD-3003 ثبت شد')
        ->and($webPush->recipients)->toBe([42])
        ->and($webPush->locale)->toBe('fa')
        ->and($webPush->dir)->toBe('rtl')
        ->and($webPush->meta)->toBe(['order_code' => 'ORD-3003']);
});

it('falls back to ltr for the default locale', function () {
    TestCase::defaultLanguage();

    $builder = app(WebPushPayloadBuilder::class);

    $webPush = $builder->build(new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_PAID->value,
        audience: 'user',
        recipients: ['database' => [42]],
        data: ['database' => ['order_code' => 'ORD-3003', 'amount' => '20.00']],
    ));

    expect($webPush->locale)->toBe('en')
        ->and($webPush->dir)->toBe('ltr')
        ->and($webPush->title)->toBe('Payment received for order ORD-3003');
});
