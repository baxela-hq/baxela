<?php

namespace Modules\Notification\Actions\User\Telegram;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Utils\Auth;
use Modules\Core\Utils\Locale;
use Modules\Notification\Actions\Webhook\Telegram\HandleTelegramUpdateAction;
use Modules\Notification\Exceptions\NotificationException;
use Modules\Notification\Exceptions\Telegram\TelegramApiException;
use Modules\Notification\Gateways\TelegramClient;
use Modules\Notification\Models\TelegramSubscription;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;

class LinkTelegramAction
{
    public function __construct(private readonly TelegramClient $telegram) {}

    /**
     * Link the caller's account to a Telegram chat, keyed by the code
     * the bot handed out on /start (preferred) or a directly entered
     * chat id.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws NotificationException
     */
    public function handle(array $data): TelegramSubscription
    {
        $cached = null;

        if (! empty($data[TelegramSubscriptionSchema::CODE])) {
            // Single-use: pull removes the entry so a leaked code cannot
            // be replayed.
            $cached = Cache::pull(HandleTelegramUpdateAction::linkCacheKey($data[TelegramSubscriptionSchema::CODE]));

            if ($cached === null) {
                throw NotificationException::telegramLinkInvalid();
            }
        }

        $chatId = (int) ($cached['chat_id'] ?? $data[TelegramSubscriptionSchema::CHAT_ID]);

        // A bot cannot open a chat with a user it has never heard from —
        // delivering the confirmation doubles as reachability validation
        // for directly entered chat ids (code-linked chats are reachable
        // by construction).
        try {
            $this->telegram->sendMessage(
                $chatId,
                __('notification::telegram.linked', [], Locale::fromRequest())
            );
        } catch (TelegramApiException) {
            throw NotificationException::telegramChatUnreachable();
        }

        // One row per account (re-linking moves the account to a new
        // chat) and one row per chat (a chat re-links to whoever
        // presented the latest code).
        TelegramSubscription::query()
            ->where(TelegramSubscriptionSchema::USER_ID, Auth::id())
            ->where(TelegramSubscriptionSchema::CHAT_ID, '!=', $chatId)
            ->delete();

        /** @var TelegramSubscription $subscription */
        $subscription = TelegramSubscription::query()->updateOrCreate(
            [TelegramSubscriptionSchema::CHAT_ID => $chatId],
            [
                TelegramSubscriptionSchema::USER_ID => Auth::id(),
                TelegramSubscriptionSchema::USERNAME => $cached['username'] ?? null,
                TelegramSubscriptionSchema::LINKED_AT => now(),
            ],
        );

        return $subscription;
    }
}
