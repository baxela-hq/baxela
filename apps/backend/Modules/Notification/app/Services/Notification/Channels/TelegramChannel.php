<?php

namespace Modules\Notification\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Modules\Notification\Exceptions\Telegram\TelegramApiException;
use Modules\Notification\Gateways\TelegramClient;
use Modules\Notification\Models\TelegramSubscription;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;
use Modules\Notification\Services\Notification\Contracts\MessageInterface;
use Modules\Notification\Services\Notification\Contracts\NotificationChannelInterface;
use Modules\Notification\Services\Notification\DTOs\Messages\TelegramMessage;
use Modules\Notification\Services\Notification\DTOs\SendResult;
use Throwable;

class TelegramChannel implements NotificationChannelInterface
{
    /** Telegram message ceiling; oversized bodies are truncated. */
    private const int MAX_MESSAGE_CHARS = 4096;

    public function __construct(private readonly TelegramClient $telegram) {}

    public function send(MessageInterface $message): SendResult
    {
        if (! $message instanceof TelegramMessage) {
            throw new \InvalidArgumentException('Invalid message type');
        }

        if (! $this->telegram->isConfigured()) {
            Log::warning('Telegram notification skipped: bot token is not configured (set TELEGRAM_BOT_TOKEN).');

            return new SendResult(success: false, error: 'telegram_not_configured');
        }

        $subscriptions = TelegramSubscription::query()
            ->whereIn(TelegramSubscriptionSchema::USER_ID, $message->recipients)
            ->get();

        if ($subscriptions->isEmpty()) {
            return new SendResult(success: false);
        }

        $text = mb_strcut($message->title."\n\n".$message->body, 0, self::MAX_MESSAGE_CHARS);

        foreach ($subscriptions as $subscription) {
            try {
                $this->telegram->sendMessage(
                    $subscription->{TelegramSubscriptionSchema::CHAT_ID},
                    $text
                );
            } catch (TelegramApiException $e) {
                // 400/403 from Telegram: chat not found or the user blocked
                // the bot — the chat is gone, drop the row so it stops
                // receiving attempts (same policy as expired push
                // endpoints). Anything else is logged and retried on the
                // next notification.
                if ($e->chatIsGone()) {
                    $subscription->delete();

                    continue;
                }

                Log::warning('Telegram delivery failed.', [
                    'chat_id' => $subscription->{TelegramSubscriptionSchema::CHAT_ID},
                    'error' => $e->description(),
                ]);
            } catch (Throwable $e) {
                report($e);
                Log::error('Failed to send Telegram notification.', $message->toArray());

                return new SendResult(success: false, error: 'telegram_send_failed');
            }
        }

        return new SendResult(success: true);
    }
}
