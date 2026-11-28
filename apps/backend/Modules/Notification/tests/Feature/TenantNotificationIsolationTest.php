<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Notification\Emails\DynamicNotification;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationSchema;
use Modules\Notification\Services\Notification\Builders\WebPushPayloadBuilder;
use Modules\Notification\Services\Notification\Channels\EmailChannel;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\Messages\EmailMessage;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Services\Notification\Templates\BladeTemplateEngine;
use Modules\Notification\Support\AdminRecipients;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantDomain;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @return array<int, mixed>
 */
function brandingTenant(string $slug, string $domain): array
{
    $tenant = Tenant::query()->create([
        TenantSchema::NAME => ucfirst($slug).' Store',
        TenantSchema::SLUG => $slug,
        TenantSchema::EMAIL => $slug.'@example.test',
        TenantSchema::STATUS => TenantSchema::STATUS_ACTIVE,
    ]);

    TenantDomain::query()->create([
        TenantDomainSchema::TENANT_ID => $tenant->getKey(),
        TenantDomainSchema::DOMAIN => $domain,
        TenantDomainSchema::STATUS => TenantDomainSchema::STATUS_VERIFIED,
        TenantDomainSchema::IS_PRIMARY => true,
        TenantDomainSchema::VERIFIED_AT => now(),
    ]);

    return [$tenant, $domain];
}

it('renders a queued notification under the active store after serving another store', function () {
    config(['app.name' => 'Wrong Installation Name']);

    [$storeA] = brandingTenant('store-a', 'store-a.example.test');
    [$storeB] = brandingTenant('store-b', 'store-b.example.test');

    $context = app(TenantContext::class);
    $engine = app(BladeTemplateEngine::class);
    $variables = ['order_code' => 'ORD-9001', 'amount' => '10.00'];

    // One worker, two stores in sequence: nothing about the second render
    // may inherit the first store's snapshots or the installation name.
    $first = $context->runForTenant(
        (int) $storeA->getKey(),
        fn () => $engine->render($variables, 'en', 'order.order.created', 'user'),
    );
    $second = $context->runForTenant(
        (int) $storeB->getKey(),
        fn () => $engine->render($variables, 'en', 'order.order.created', 'user'),
    );

    expect($first->body)->toContain('Store-a Store', 'https://store-a.example.test')
        ->and($first->body)->not->toContain('Wrong Installation Name', 'store-b.example.test')
        ->and($second->body)->toContain('Store-b Store', 'https://store-b.example.test')
        ->and($second->body)->not->toContain('Wrong Installation Name', 'store-a.example.test');
});

it('stamps a dispatched database notification with the active store', function () {
    TestCase::defaultLanguage();

    [$storeA] = brandingTenant('queue-a', 'queue-a.example.test');
    [$storeB] = brandingTenant('queue-b', 'queue-b.example.test');
    $user = User::factory()->active()->create();

    $dispatcher = app(NotificationDispatcherInterface::class);
    $messageFor = fn (string $orderCode): NotificationMessage => new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: 'user',
        recipients: ['database' => [$user->id]],
        data: ['database' => ['order_code' => $orderCode, 'amount' => '18.00']],
        // The dispatcher iterates values; a keyed map keeps the documented
        // channel shape while selecting only the database channel here.
        channel: ['database' => 'database'],
        locale: 'en',
    );

    $context = app(TenantContext::class);
    $context->runForTenant((int) $storeA->getKey(), fn () => $dispatcher->dispatch($messageFor('ORD-A')));
    $context->runForTenant((int) $storeB->getKey(), fn () => $dispatcher->dispatch($messageFor('ORD-B')));

    // Notifications are store-owned: read each one inside its own tenant
    // scope rather than from the test harness's default tenant.
    $first = $context->runForTenant((int) $storeA->getKey(),
        fn () => Notification::query()->where(NotificationSchema::USER_ID, $user->id)->firstOrFail());
    $second = $context->runForTenant((int) $storeB->getKey(),
        fn () => Notification::query()->where(NotificationSchema::USER_ID, $user->id)->firstOrFail());

    expect($first->{TenantColumn::TENANT_ID})->toBe($storeA->getKey())
        ->and($first->body)->toContain('ORD-A')
        ->and($first->body)->not->toContain('ORD-B')
        ->and($second->{TenantColumn::TENANT_ID})->toBe($storeB->getKey())
        ->and($second->body)->toContain('ORD-B')
        ->and($second->body)->not->toContain('ORD-A');
});

it('names the active store in the web push payload', function () {
    TestCase::defaultLanguage();

    [, $domain] = brandingTenant('push-store', 'push.example.test');

    $message = new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: 'user',
        recipients: ['database' => [42]],
        data: ['database' => ['order_code' => 'ORD-9002', 'amount' => '12.00']],
        locale: 'en',
    );

    $webPush = app(TenantContext::class)->runForTenant(
        (int) Tenant::query()->where(TenantSchema::SLUG, 'push-store')->value(TenantSchema::ID),
        fn () => app(WebPushPayloadBuilder::class)->build($message),
    );

    expect($webPush->storeName)->toBe('Push-store Store')
        ->and($webPush->storefrontUrl)->toBe('https://'.$domain)
        ->and($webPush->body)->toContain('ORD-9002');
});

it('names the active store as the email from-name', function () {
    Mail::fake();

    [$store] = brandingTenant('mail-store', 'mail.example.test');

    app(TenantContext::class)->runForTenant(
        (int) $store->getKey(),
        fn () => app(EmailChannel::class)->send(new EmailMessage(
            recipients: ['ops@example.test'],
            subject: 'Order update',
            body: 'Hello',
        )),
    );

    Mail::assertSent(DynamicNotification::class, fn (DynamicNotification $mail): bool => $mail->storeName === 'Mail-store Store');
});

it('restricts configured admin recipients to active staff of the active store', function () {
    [$storeA] = brandingTenant('staff-a', 'staff-a.example.test');
    $storeB = Tenant::query()->create([
        TenantSchema::NAME => 'Staff B Store',
        TenantSchema::SLUG => 'staff-b',
        TenantSchema::EMAIL => 'staff-b@example.test',
        TenantSchema::STATUS => TenantSchema::STATUS_ACTIVE,
    ]);

    $access = app(AccessGatewayInterface::class);
    $roleA = app(TenantContext::class)->runForTenant(
        (int) $storeA->getKey(),
        fn () => $access->seedTenantRoles((int) $storeA->getKey()),
    );
    $roleB = app(TenantContext::class)->runForTenant(
        (int) $storeB->getKey(),
        fn () => $access->seedTenantRoles((int) $storeB->getKey()),
    );

    $staffA = User::factory()->active()->create();
    $staffB = User::factory()->active()->create();
    $inactive = User::factory()->create(['is_active' => false]);

    $access->assignTenantRole($staffA->id, (int) $storeA->getKey(), $roleA);
    $access->assignTenantRole($staffB->id, (int) $storeB->getKey(), $roleB);
    $access->assignTenantRole($inactive->id, (int) $storeA->getKey(), $roleA);

    // A stale env list may narrow the fan-out; it can never widen it to
    // another store's staff.
    config(['notification.notifications.admin_recipients.database' => [(string) $staffB->id]]);

    $recipients = app(TenantContext::class)->runForTenant(
        (int) $storeA->getKey(),
        fn () => AdminRecipients::databaseIds(),
    );

    expect($recipients)->toBe([])
        ->and(app(TenantContext::class)->runForTenant(
            (int) $storeA->getKey(),
            fn () => $access->tenantAdminUserIds((int) $storeA->getKey()),
        ))->toBe([$staffA->id]);
});
