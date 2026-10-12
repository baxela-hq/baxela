<?php

namespace Modules\Notification\Actions\User\Telegram;

use Modules\Core\Utils\Auth;
use Modules\Core\Utils\Locale;
use Modules\Notification\Exceptions\NotificationException;
use Modules\Notification\Gateways\TelegramClient;
use Modules\Notification\Models\TelegramSubscription;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;

class SendTelegramTestAction
{
    public function __construct(private readonly TelegramClient $telegram) {}

    /**
     * Deliver a canary message to the caller's linked chat so the whole
     * chain (token, subscription, delivery) can be verified from the UI.
     *
     * @throws NotificationException
     */
    public function handle(): void
    {
        $subscription = TelegramSubscription::query()
            ->where(TelegramSubscriptionSchema::USER_ID, Auth::id())
            ->first();

        if ($subscription === null) {
            throw NotificationException::telegramNotLinked();
        }

        $this->telegram->sendMessage(
            $subscription->{TelegramSubscriptionSchema::CHAT_ID},
            __('notification::telegram.test', [], Locale::fromRequest())
        );
    }
}
