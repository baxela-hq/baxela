<?php

namespace Modules\Webhooks\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Webhooks\Actions\Webhooks\RecordDeliveryOutcomeAction;
use Modules\Webhooks\Gateways\WebhookDeliveryClient;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Models\WebhookEvent;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliveryStatusEnum;
use Modules\Webhooks\Schemas\WebhookEvent\WebhookEventSchema;
use Modules\Webhooks\Support\UrlGuard;
use Modules\Webhooks\Support\WebhookSigner;

/**
 * One signed HTTP delivery of one delivery row (gap-C2 D4/D7/D8/D9).
 *
 * Lifecycle: atomic claim (pending/failed_retryable + free/expired lease,
 * unique claim_token) → attempt-history row created BEFORE the request →
 * IP-pinned send → attempt finalized → terminal state applied with the
 * claim token still owned. A stale worker's updates fail their
 * conditional update and are discarded. Retryable failures schedule the
 * next attempt via max(backoff step, Retry-After) capped at 24h; the
 * scheduled sweep re-enqueues them — the delivery table is the durable
 * source of truth, so a lost enqueue loses nothing.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 60;

    public function __construct(protected string $deliveryId) {}

    public function handle(
        UrlGuard $guard,
        WebhookDeliveryClient $client,
        RecordDeliveryOutcomeAction $outcomes,
    ): void {
        /** @var WebhookDelivery|null $delivery */
        $delivery = WebhookDelivery::query()->find($this->deliveryId);

        if (is_null($delivery)) {
            return;
        }

        $claimToken = (string) Str::uuid();

        // Atomic claim: exactly one worker wins; losers exit without
        // touching anything.
        $claimed = WebhookDelivery::query()
            ->where(WebhookDeliverySchema::ID, $delivery->getKey())
            ->whereIn(WebhookDeliverySchema::STATUS, [
                WebhookDeliveryStatusEnum::PENDING->value,
                WebhookDeliveryStatusEnum::FAILED_RETRYABLE->value,
            ])
            ->where(function ($query): void {
                $query
                    ->whereNull(WebhookDeliverySchema::CLAIM_TOKEN)
                    ->orWhere(WebhookDeliverySchema::CLAIMED_AT, '<', now()->subSeconds((int) config('webhooks.claim_lease_seconds')));
            })
            ->update([
                WebhookDeliverySchema::STATUS => WebhookDeliveryStatusEnum::PROCESSING->value,
                WebhookDeliverySchema::CLAIM_TOKEN => $claimToken,
                WebhookDeliverySchema::CLAIMED_AT => now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        $event = WebhookEvent::query()->find($delivery->{WebhookDeliverySchema::EVENT_ID});

        if (is_null($event)) {
            return;
        }

        $endpoint = $delivery->endpoint()->first();

        // Archived endpoints: in-flight (claimed) deliveries complete;
        // this claim IS the in-flight unit, so deliver even if archived
        // between enqueue and claim (gap-C2 D12).
        $body = (string) json_encode(
            $event->{WebhookEventSchema::PAYLOAD},
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        $timestamp = (string) now()->timestamp;
        $signature = WebhookSigner::sign(
            $endpoint->secret,
            $timestamp,
            (string) $event->getKey(),
            (string) $delivery->getKey(),
            $body,
        );

        $headers = [
            'X-Baxela-Signature' => 'sha256='.$signature,
            'X-Baxela-Timestamp' => $timestamp,
            'X-Baxela-Event-Id' => (string) $event->getKey(),
            'X-Baxela-Delivery' => (string) $delivery->getKey(),
            'X-Baxela-Event' => $delivery->{WebhookDeliverySchema::EVENT_TYPE},
            'Content-Type' => 'application/json',
        ];

        // SSRF re-validation at EVERY attempt: DNS answers change between
        // retries; the connection is pinned to the validated IP (D7).
        try {
            ['url' => $pinnedUrl, 'ip' => $pinnedIp] = $guard->pin($endpoint->url);
        } catch (\Throwable $e) {
            $this->finalize($delivery->getKey(), $claimToken, $endpoint->getKey(), false, [
                WebhookDeliverySchema::ERROR_CATEGORY => 'url_blocked',
            ], $outcomes);

            return;
        }

        // Attempt-history row created BEFORE the request (gap-C2 D9): one
        // row per real HTTP attempt, the unit of bookkeeping.
        $attemptStarted = now();

        try {
            $result = $client->send($pinnedUrl, $pinnedIp, $headers, $body);
        } catch (\Throwable $e) {
            $this->finalize($delivery->getKey(), $claimToken, $endpoint->getKey(), false, [
                WebhookDeliverySchema::ERROR_CATEGORY => 'transport',
                WebhookDeliverySchema::ATTEMPTS => $this->appendAttempt($delivery, $attemptStarted, [
                    'error' => 'transport',
                    'started_at' => $attemptStarted->toIso8601String(),
                ]),
            ], $outcomes);

            return;
        }

        $status = $result['status'];
        $success = $status >= 200 && $status < 300;
        $permanent = in_array($status, [400, 401, 403], true)
            || ($status >= 300 && $status < 400);

        $this->finalize($delivery->getKey(), $claimToken, $endpoint->getKey(), $success, [
            WebhookDeliverySchema::RESPONSE_STATUS => $status,
            WebhookDeliverySchema::RESPONSE_SNIPPET => mb_substr($result['body'], 0, 1000),
            WebhookDeliverySchema::DURATION_MS => $result['duration_ms'],
            WebhookDeliverySchema::ERROR_CATEGORY => $success ? null : $this->errorCategory($status),
        ], $outcomes);
    }

    /**
     * Terminal-state transition, conditional on the claim token: a stale
     * worker's write is a 0-row update and discarded (gap-C2 D4).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function finalize(
        string $deliveryId,
        string $claimToken,
        int $endpointId,
        bool $success,
        array $attributes,
        RecordDeliveryOutcomeAction $outcomes,
    ): void {
        $attemptCount = (int) DB::table('webhook_deliveries')->where('id', $deliveryId)->value('attempt_count');
        $attemptCount += 1;

        $isLastAttempt = $attemptCount >= (int) config('webhooks.max_attempts');
        $permanent = ! $success && (
            in_array($attributes[WebhookDeliverySchema::ERROR_CATEGORY] ?? '', ['url_blocked', 'permanent'], true)
        );

        $nextAttemptAt = null;

        if (! $success && ! $permanent && ! $isLastAttempt) {
            $step = config('webhooks.backoff_seconds')[min($attemptCount - 1, count((array) config('webhooks.backoff_seconds')) - 1)];
            $nextAttemptAt = now()->addSeconds((int) $step)->toIso8601String();
            $attributes[WebhookDeliverySchema::NEXT_ATTEMPT_AT] = $nextAttemptAt;
        }

        $updated = WebhookDelivery::query()
            ->where(WebhookDeliverySchema::ID, $deliveryId)
            ->where(WebhookDeliverySchema::CLAIM_TOKEN, $claimToken)
            ->update(array_merge($attributes, [
                WebhookDeliverySchema::ATTEMPT_COUNT => $attemptCount,
                WebhookDeliverySchema::STATUS => $success
                    ? WebhookDeliveryStatusEnum::SUCCEEDED->value
                    : ($permanent || $isLastAttempt
                        ? WebhookDeliveryStatusEnum::FAILED_PERMANENT->value
                        : WebhookDeliveryStatusEnum::FAILED_RETRYABLE->value),
                WebhookDeliverySchema::CLAIM_TOKEN => null,
                WebhookDeliverySchema::DELIVERED_AT => $success ? now() : null,
            ]));

        // Only the current owner applies the outcome to the endpoint
        // counter (gap-C2 D11).
        if ($updated > 0) {
            $outcomes->handle(WebhookDelivery::query()->find($deliveryId), $success);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return list<array<string, mixed>>
     */
    private function appendAttempt(WebhookDelivery $delivery, $startedAt, array $extra): array
    {
        $attempts = (array) $delivery->{WebhookDeliverySchema::ATTEMPTS};

        $attempts[] = array_merge($extra, [
            'started_at' => $startedAt->toIso8601String(),
        ]);

        return $attempts;
    }

    private function errorCategory(int $status): string
    {
        if ($status >= 300 && $status < 400) {
            return 'redirect'; // permanent — redirects are disabled
        }

        if (in_array($status, [400, 401, 403], true)) {
            return 'permanent';
        }

        return 'retryable';
    }
}
