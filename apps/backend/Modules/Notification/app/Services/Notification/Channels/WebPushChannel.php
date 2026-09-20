<?php

namespace Modules\Notification\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Modules\Notification\Models\PushSubscription;
use Modules\Notification\Schemas\PushSubscription\PushSubscriptionSchema;
use Modules\Notification\Services\Notification\Contracts\MessageInterface;
use Modules\Notification\Services\Notification\Contracts\NotificationChannelInterface;
use Modules\Notification\Services\Notification\DTOs\Messages\WebPushMessage;
use Modules\Notification\Services\Notification\DTOs\SendResult;
use Throwable;

class WebPushChannel implements NotificationChannelInterface
{
    /** Push service payload ceiling; oversized payloads are rejected. */
    private const MAX_PAYLOAD_BYTES = 4096;

    public function send(MessageInterface $message): SendResult
    {
        if (! $message instanceof WebPushMessage) {
            throw new \InvalidArgumentException('Invalid message type');
        }

        $vapid = $this->vapidConfig();

        if ($vapid === null) {
            Log::warning('Web push notification skipped: VAPID keys are not configured (run `php artisan notification:generate-vapid`).');

            return new SendResult(success: false, error: 'vapid_not_configured');
        }

        $subscriptions = PushSubscription::query()
            ->whereIn(PushSubscriptionSchema::USER_ID, $message->recipients)
            ->get();

        if ($subscriptions->isEmpty()) {
            return new SendResult(success: false);
        }

        try {
            $webPush = new WebPush(['VAPID' => $vapid]);
            $payload = $this->encodePayload($message);

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->{PushSubscriptionSchema::ENDPOINT},
                        'keys' => [
                            'p256dh' => $subscription->{PushSubscriptionSchema::P256DH},
                            'auth' => $subscription->{PushSubscriptionSchema::AUTH},
                        ],
                    ]),
                    $payload
                );
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    continue;
                }

                // 404/410 from the push service: the registration is gone
                // (browser cleaned it up) — drop the row so it stops
                // receiving attempts.
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::query()
                        ->where(PushSubscriptionSchema::ENDPOINT, $report->getEndpoint())
                        ->delete();

                    continue;
                }

                Log::warning('Web push delivery failed.', [
                    'endpoint' => $report->getEndpoint(),
                    'reason' => $report->getReason(),
                ]);
            }
        } catch (Throwable $e) {
            report($e);
            Log::error('Failed to send web push notification.', $message->toArray());

            return new SendResult(success: false, error: 'webpush_send_failed');
        }

        return new SendResult(success: true);
    }

    /**
     * @return array{subject: string, publicKey: string, privateKey: string}|null
     */
    private function vapidConfig(): ?array
    {
        $vapid = config('notification.notifications.webpush.vapid', []);

        if (empty($vapid['public_key']) || empty($vapid['private_key'])) {
            return null;
        }

        return VAPID::validate([
            'subject' => $vapid['subject'] ?? '',
            'publicKey' => $vapid['public_key'],
            'privateKey' => $vapid['private_key'],
        ]);
    }

    /**
     * JSON payload the service worker reads: {title, body, code, meta,
     * locale, dir}. Meta is dropped, then the body truncated, to stay
     * under the push service's size limit.
     */
    private function encodePayload(WebPushMessage $message): string
    {
        $payload = [
            'title' => $message->title,
            'body' => $message->body,
            'code' => $message->code,
            'meta' => empty($message->meta) ? null : $message->meta,
            'locale' => $message->locale,
            'dir' => $message->dir,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if (strlen($json) > self::MAX_PAYLOAD_BYTES) {
            unset($payload['meta']);
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        if (strlen($json) > self::MAX_PAYLOAD_BYTES) {
            $payload['body'] = mb_strcut($payload['body'], 0, 2000);
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        return $json;
    }
}
