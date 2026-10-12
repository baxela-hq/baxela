<?php

namespace Modules\Notification\Actions\Webhook\Telegram;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Notification\Exceptions\NotificationException;
use Modules\Notification\Exceptions\Telegram\TelegramApiException;
use Modules\Notification\Gateways\TelegramClient;
use Modules\Notification\Models\TelegramSubscription;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;

class HandleTelegramUpdateAction
{
    /** Minutes a /start linking code stays valid. */
    public const int LINK_CODE_TTL_MINUTES = 10;

    public function __construct(private readonly TelegramClient $telegram) {}

    /**
     * HTTP entry (webhook): verify the secret-token header Telegram signs
     * every update with, then process the payload.
     *
     * @throws NotificationException
     */
    public function handleRequest(Request $request): void
    {
        $secret = (string) config('notification.notifications.telegram.webhook_secret');

        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''))) {
            throw NotificationException::telegramWebhookUnauthorized();
        }

        $this->handleUpdate((array) $request->json()->all());
    }

    /**
     * Process one raw update payload (shared with the dev poll command).
     *
     * @param  array<string, mixed>  $update
     */
    public function handleUpdate(array $update): void
    {
        // Bot kicked out of the chat (user blocked it) — no reply is
        // possible, just drop the linked account.
        $memberStatus = $update['my_chat_member']['new_chat_member']['status'] ?? null;

        if (in_array($memberStatus, ['kicked', 'left'], true)) {
            TelegramSubscription::query()
                ->where(TelegramSubscriptionSchema::CHAT_ID, (int) ($update['my_chat_member']['from']['id'] ?? 0))
                ->delete();

            return;
        }

        $message = $update['message'] ?? null;

        if (! is_array($message)) {
            return;
        }

        $chatId = (int) ($message['chat']['id'] ?? 0);

        if ($chatId === 0) {
            return;
        }

        // Reply in the user's own Telegram language, defaulting to English.
        $locale = str_starts_with((string) ($message['from']['language_code'] ?? ''), 'fa') ? 'fa' : 'en';
        $text = trim((string) ($message['text'] ?? ''));

        match (true) {
            $text === '/start' => $this->handleStart(
                $chatId,
                $message['from']['username'] ?? null,
                $locale
            ),
            in_array($text, ['/unlink', '/stop'], true) => $this->handleUnlink($chatId, $locale),
            default => $this->reply($chatId, 'help', $locale),
        };
    }

    /**
     * Cache key holding the chat (and username) behind a /start code until
     * the account-link endpoint consumes it.
     */
    public static function linkCacheKey(string $code): string
    {
        return "telegram:link:{$code}";
    }

    private function handleStart(int $chatId, ?string $username, string $locale): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put(self::linkCacheKey($code), [
            'chat_id' => $chatId,
            'username' => $username,
        ], now()->addMinutes(self::LINK_CODE_TTL_MINUTES));

        $this->reply($chatId, 'start', $locale, [
            'code' => $code,
            'chat_id' => $chatId,
            'minutes' => self::LINK_CODE_TTL_MINUTES,
        ]);
    }

    private function handleUnlink(int $chatId, string $locale): void
    {
        TelegramSubscription::query()
            ->where(TelegramSubscriptionSchema::CHAT_ID, $chatId)
            ->delete();

        $this->reply($chatId, 'unlinked', $locale);
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    private function reply(int $chatId, string $key, string $locale, array $replace = []): void
    {
        try {
            $this->telegram->sendMessage($chatId, __("notification::telegram.{$key}", $replace, $locale));
        } catch (TelegramApiException $e) {
            // The bot can no longer write to this chat (blocked between the
            // update and our reply) — nothing actionable, log and move on.
            Log::warning('Telegram bot reply failed.', ['chat_id' => $chatId, 'error' => $e->description()]);
        }
    }
}
