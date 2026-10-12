<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Auth\Models\User;
use Modules\Notification\Actions\Webhook\Telegram\HandleTelegramUpdateAction;
use Modules\Notification\Models\TelegramSubscription;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

const TELEGRAM_SECRET = 'test-webhook-secret';

/**
 * @return array<string, mixed>
 */
function telegramUpdate(string $text, int $chatId = 501, string $languageCode = 'en', ?string $username = 'johndoe'): array
{
    return [
        'update_id' => 1,
        'message' => [
            'message_id' => 10,
            'text' => $text,
            'chat' => ['id' => $chatId],
            'from' => ['id' => $chatId, 'username' => $username, 'language_code' => $languageCode],
        ],
    ];
}

function configureTelegram(): void
{
    config([
        'notification.notifications.telegram.bot_token' => 'test-token',
        'notification.notifications.telegram.webhook_secret' => TELEGRAM_SECRET,
    ]);
}

function fakeTelegramSuccess(): void
{
    Http::fake(['telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
}

function fakeTelegramBlocked(): void
{
    Http::fake(['telegram.org/*' => Http::response([
        'ok' => false,
        'error_code' => 403,
        'description' => 'Forbidden: bot was blocked by the user',
    ], 403)]);
}

function webhookHeaders(): array
{
    return ['X-Telegram-Bot-Api-Secret-Token' => TELEGRAM_SECRET];
}

function cacheStartCode(int $chatId = 501, string $username = 'johndoe'): string
{
    $code = '654321';

    Cache::put(HandleTelegramUpdateAction::linkCacheKey($code), [
        'chat_id' => $chatId,
        'username' => $username,
    ], now()->addMinutes(10));

    return $code;
}

// --- webhook (bot conversation) ---

it('rejects telegram webhook updates without the secret token', function () {
    configureTelegram();

    $this->postJson($this->baseUrl('/webhook/telegram'), telegramUpdate('/start'))
        ->assertStatus(403);

    $this->postJson($this->baseUrl('/webhook/telegram'), telegramUpdate('/start'), [
        'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
    ])->assertStatus(403);
});

it('replies to /start with a cached linking code', function () {
    configureTelegram();
    fakeTelegramSuccess();

    $this->postJson($this->baseUrl('/webhook/telegram'), telegramUpdate('/start'), webhookHeaders())
        ->assertOk()
        ->assertJsonPath('data.received', true);

    $reply = collect(Http::recorded())
        ->first(fn ($pair) => str_contains($pair[0]->url(), 'sendMessage'))[0]
        ->data()['text'];

    preg_match('/\d{6}/', $reply, $match);
    expect($match)->not->toBeEmpty()
        ->and(Cache::get(HandleTelegramUpdateAction::linkCacheKey($match[0])))
        ->toBe(['chat_id' => 501, 'username' => 'johndoe']);
});

it('replies in the chat language', function () {
    configureTelegram();
    fakeTelegramSuccess();

    $this->postJson($this->baseUrl('/webhook/telegram'), telegramUpdate('/start', languageCode: 'fa-IR'), webhookHeaders())
        ->assertOk();

    $reply = collect(Http::recorded())
        ->first(fn ($pair) => str_contains($pair[0]->url(), 'sendMessage'))[0]
        ->data()['text'];

    expect($reply)->toContain('کد اتصال');
});

it('unlinks the chat on /unlink', function () {
    configureTelegram();
    fakeTelegramSuccess();

    $user = User::factory()->create();
    TelegramSubscription::query()->create([
        TelegramSubscriptionSchema::USER_ID => $user->id,
        TelegramSubscriptionSchema::CHAT_ID => 501,
    ]);

    $this->postJson($this->baseUrl('/webhook/telegram'), telegramUpdate('/unlink'), webhookHeaders())
        ->assertOk();

    expect(TelegramSubscription::query()->count())->toBe(0);
});

it('unlinks the chat when the bot is blocked', function () {
    configureTelegram();
    fakeTelegramSuccess();

    $user = User::factory()->create();
    TelegramSubscription::query()->create([
        TelegramSubscriptionSchema::USER_ID => $user->id,
        TelegramSubscriptionSchema::CHAT_ID => 501,
    ]);

    $this->postJson($this->baseUrl('/webhook/telegram'), [
        'my_chat_member' => [
            'from' => ['id' => 501],
            'new_chat_member' => ['status' => 'kicked'],
        ],
    ], webhookHeaders())->assertOk();

    expect(TelegramSubscription::query()->count())->toBe(0);
});

// --- account linking ---

it('links the signed-in account via a start code', function () {
    configureTelegram();
    fakeTelegramSuccess();

    $user = User::factory()->create();
    $code = cacheStartCode();

    $this->actingAs($user, 'sanctum')
        ->postJson($this->baseUrl('/user/telegram/link'), [
            TelegramSubscriptionSchema::CODE => $code,
            TelegramSubscriptionSchema::CHAT_ID => null,
        ])
        ->assertCreated()
        ->assertJsonPath('data.chat_id', 501);

    $subscription = TelegramSubscription::query()->sole();

    expect($subscription->{TelegramSubscriptionSchema::USER_ID})->toBe($user->id)
        ->and($subscription->{TelegramSubscriptionSchema::USERNAME})->toBe('johndoe')
        // codes are single-use
        ->and(Cache::has(HandleTelegramUpdateAction::linkCacheKey($code)))->toBeFalse();
});

it('rejects an expired or unknown linking code', function () {
    configureTelegram();
    fakeTelegramSuccess();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson($this->baseUrl('/user/telegram/link'), [
            TelegramSubscriptionSchema::CODE => '000000',
            TelegramSubscriptionSchema::CHAT_ID => null,
        ])
        ->assertStatus(400);

    expect(TelegramSubscription::query()->count())->toBe(0);
});

it('links a directly entered chat id after the bot proves it can message it', function () {
    configureTelegram();
    fakeTelegramSuccess();

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson($this->baseUrl('/user/telegram/link'), [
            TelegramSubscriptionSchema::CODE => null,
            TelegramSubscriptionSchema::CHAT_ID => 501,
        ])
        ->assertCreated();

    expect(TelegramSubscription::query()->sole()->{TelegramSubscriptionSchema::USER_ID})->toBe($user->id);
});

it('rejects a chat id the bot cannot message', function () {
    configureTelegram();
    fakeTelegramBlocked();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson($this->baseUrl('/user/telegram/link'), [
            TelegramSubscriptionSchema::CODE => null,
            TelegramSubscriptionSchema::CHAT_ID => 501,
        ])
        ->assertStatus(400);

    expect(TelegramSubscription::query()->count())->toBe(0);
});

it('keeps one row per account and per chat on relink', function () {
    configureTelegram();
    fakeTelegramSuccess();

    $first = User::factory()->create();
    $second = User::factory()->create();

    // same chat moving between accounts (row re-assigned, not duplicated)
    $this->actingAs($first, 'sanctum')
        ->postJson($this->baseUrl('/user/telegram/link'), [
            TelegramSubscriptionSchema::CODE => cacheStartCode(),
            TelegramSubscriptionSchema::CHAT_ID => null,
        ])->assertCreated();

    $this->actingAs($second, 'sanctum')
        ->postJson($this->baseUrl('/user/telegram/link'), [
            TelegramSubscriptionSchema::CODE => cacheStartCode(),
            TelegramSubscriptionSchema::CHAT_ID => null,
        ])->assertOk();

    expect(TelegramSubscription::query()->count())->toBe(1)
        ->and(TelegramSubscription::query()->sole()->{TelegramSubscriptionSchema::USER_ID})->toBe($second->id);

    // one account moving between chats (old chat row replaced)
    $this->actingAs($second, 'sanctum')
        ->postJson($this->baseUrl('/user/telegram/link'), [
            TelegramSubscriptionSchema::CODE => cacheStartCode(chatId: 502),
            TelegramSubscriptionSchema::CHAT_ID => null,
        ])->assertCreated();

    expect(TelegramSubscription::query()->count())->toBe(1)
        ->and(TelegramSubscription::query()->sole()->{TelegramSubscriptionSchema::CHAT_ID})->toBe(502);
});

it('unlinks only the callers own chat', function () {
    configureTelegram();

    $user = User::factory()->create();
    $other = User::factory()->create();

    TelegramSubscription::query()->create([
        TelegramSubscriptionSchema::USER_ID => $user->id,
        TelegramSubscriptionSchema::CHAT_ID => 501,
    ]);
    TelegramSubscription::query()->create([
        TelegramSubscriptionSchema::USER_ID => $other->id,
        TelegramSubscriptionSchema::CHAT_ID => 502,
    ]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson($this->baseUrl('/user/telegram/subscription'))
        ->assertNoContent();

    expect(TelegramSubscription::query()->count())->toBe(1)
        ->and(TelegramSubscription::query()->sole()->{TelegramSubscriptionSchema::USER_ID})->toBe($other->id);
});

it('sends a test message to the linked chat only', function () {
    configureTelegram();
    fakeTelegramSuccess();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson($this->baseUrl('/user/telegram/test'))
        ->assertStatus(404);

    $user = User::factory()->create();
    TelegramSubscription::query()->create([
        TelegramSubscriptionSchema::USER_ID => $user->id,
        TelegramSubscriptionSchema::CHAT_ID => 501,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson($this->baseUrl('/user/telegram/test'))
        ->assertOk()
        ->assertJsonPath('data.sent', true);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && ($request->data()['chat_id'] ?? null) === 501);
});

// --- public bot identity ---

it('exposes the bot link when configured and nulls otherwise', function () {
    configureTelegram();
    Http::fake([
        'telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 7, 'username' => 'baxela_bot']]),
        'telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);

    $this->getJson($this->baseUrl('/telegram/bot-info'))
        ->assertOk()
        ->assertJsonPath('data.bot_username', 'baxela_bot')
        ->assertJsonPath('data.bot_url', 'https://t.me/baxela_bot');

    config(['notification.notifications.telegram.bot_token' => null]);
    Cache::forget('telegram:bot:username');

    $this->getJson($this->baseUrl('/telegram/bot-info'))
        ->assertOk()
        ->assertJsonPath('data.bot_username', null);
});

// --- delivery ---

it('delivers notifications to linked chats from the database recipients', function () {
    configureTelegram();
    fakeTelegramSuccess();

    $user = User::factory()->create();
    TelegramSubscription::query()->create([
        TelegramSubscriptionSchema::USER_ID => $user->id,
        TelegramSubscriptionSchema::CHAT_ID => 501,
    ]);

    app(NotificationDispatcherInterface::class)->dispatch(new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: 'user',
        recipients: ['database' => [$user->id]], // no explicit telegram key — builder falls back
        data: ['database' => ['order_code' => 'ORD-3003', 'amount' => '20.00']],
        channel: ['telegram'],
    ));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && ($request->data()['chat_id'] ?? null) === 501
        && str_contains((string) ($request->data()['text'] ?? ''), 'ORD-3003'));
});

it('drops the subscription when delivery is rejected as blocked', function () {
    configureTelegram();
    fakeTelegramBlocked();

    $user = User::factory()->create();
    TelegramSubscription::query()->create([
        TelegramSubscriptionSchema::USER_ID => $user->id,
        TelegramSubscriptionSchema::CHAT_ID => 501,
    ]);

    app(NotificationDispatcherInterface::class)->dispatch(new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: 'user',
        recipients: ['telegram' => [$user->id]],
        data: ['database' => ['order_code' => 'ORD-3003', 'amount' => '20.00']],
        channel: ['telegram'],
    ));

    expect(TelegramSubscription::query()->count())->toBe(0);
});

it('no-ops the telegram channel when the bot token is not configured', function () {
    config(['notification.notifications.telegram.bot_token' => null]);

    $user = User::factory()->create();
    TelegramSubscription::query()->create([
        TelegramSubscriptionSchema::USER_ID => $user->id,
        TelegramSubscriptionSchema::CHAT_ID => 501,
    ]);

    app(NotificationDispatcherInterface::class)->dispatch(new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: 'user',
        recipients: ['telegram' => [$user->id]],
        data: ['database' => ['order_code' => 'ORD-3003', 'amount' => '20.00']],
        channel: ['telegram'],
    ));

    Http::assertNothingSent();
    expect(TelegramSubscription::query()->count())->toBe(1);
});
