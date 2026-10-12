<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Builders\TelegramPayloadBuilder;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

uses(RefreshDatabase::class);

it('renders telegram from the database variables and recipients', function () {
    $this->seed(CoreDatabaseSeeder::class);

    $builder = app(TelegramPayloadBuilder::class);

    $telegram = $builder->build(new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: 'user',
        recipients: ['database' => [42]],
        data: ['database' => ['order_code' => 'ORD-3003', 'amount' => '20.00']],
        locale: 'fa',
        meta: ['order_code' => 'ORD-3003'],
    ));

    expect($telegram->title)->toBe('سفارش ORD-3003 ثبت شد')
        ->and($telegram->recipients)->toBe([42])
        ->and($telegram->locale)->toBe('fa')
        ->and($telegram->meta)->toBe(['order_code' => 'ORD-3003']);
});

it('prefers dedicated telegram variables and recipients when set', function () {
    $this->seed(CoreDatabaseSeeder::class);

    $builder = app(TelegramPayloadBuilder::class);

    $telegram = $builder->build(new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: 'user',
        recipients: [
            'database' => [42],
            'telegram' => [7],
        ],
        data: ['database' => ['order_code' => 'ORD-3003', 'amount' => '20.00']],
    ));

    // no explicit locale — the app locale carries through, whichever it is
    expect($telegram->recipients)->toBe([7])
        ->and($telegram->locale)->toBe(app()->getLocale())
        ->and($telegram->title)->not->toBeEmpty()
        ->and($telegram->body)->toContain('ORD-3003');
});
