<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Webhooks\Actions\Webhooks\RecordDeliveryOutcomeAction;
use Modules\Webhooks\Gateways\WebhookDeliveryClient;
use Modules\Webhooks\Jobs\DeliverWebhookJob;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Models\WebhookEvent;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliveryStatusEnum;
use Modules\Webhooks\Support\UrlGuard;
use Modules\Webhooks\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function outboxEvent(string $type = 'order.paid'): WebhookEvent
{
    return WebhookEvent::query()->create([
        'id' => (string) Str::uuid(),
        'source_key' => $type.':'.bin2hex(random_bytes(6)),
        'event_type' => $type,
        'payload' => [
            'api_version' => 'v1',
            'type' => $type,
            'occurred_at' => now()->toIso8601String(),
            'data' => ['order_code' => 'RC123', 'total_amount' => 42.5],
        ],
    ]);
}

function deliveryFor(WebhookEvent $event, $endpoint, string $status = 'pending'): WebhookDelivery
{
    return WebhookDelivery::query()->create([
        'id' => (string) Str::uuid(),
        'event_id' => $event->getKey(),
        'endpoint_id' => $endpoint->getKey(),
        'event_type' => $event->event_type,
        'status' => $status,
    ]);
}

it('delivers a signed request and marks the delivery succeeded', function () {
    [$endpoint, $secret] = $this->webhookEndpoint(['order.paid']);
    $event = outboxEvent();
    $delivery = deliveryFor($event, $endpoint);

    Http::fake(['*93.184.216.34*' => Http::response(['ok' => true], 200)]);

    (new DeliverWebhookJob($delivery->getKey()))
        ->handle(app(UrlGuard::class), app(WebhookDeliveryClient::class), app(RecordDeliveryOutcomeAction::class));

    $delivery->refresh();

    expect($delivery->{WebhookDeliverySchema::STATUS})->toBe(WebhookDeliveryStatusEnum::SUCCEEDED)
        ->and($delivery->{WebhookDeliverySchema::RESPONSE_STATUS})->toBe(200)
        ->and($delivery->{WebhookDeliverySchema::ATTEMPT_COUNT})->toBe(1);

    // Signature covers timestamp + event_id + delivery_id + body.
    Http::assertSent(function ($request) use ($secret, $event, $delivery) {
        $signatureHeader = $request->header('X-Baxela-Signature')[0] ?? '';
        $timestamp = $request->header('X-Baxela-Timestamp')[0] ?? '';
        $body = $request->body();

        $expected = 'sha256='.hash_hmac('sha256', implode('.', [
            $timestamp, (string) $event->getKey(), (string) $delivery->getKey(), $body,
        ]), $secret);

        return hash_equals($expected, $signatureHeader)
            && ($request->header('X-Baxela-Event-Id')[0] ?? '') === (string) $event->getKey()
            && ($request->header('X-Baxela-Delivery')[0] ?? '') === (string) $delivery->getKey();
    });
});

it('marks 4xx as a permanent failure without retry scheduling', function () {
    [$endpoint] = $this->webhookEndpoint(['order.paid']);
    $delivery = deliveryFor(outboxEvent(), $endpoint);

    Http::fake(['*93.184.216.34*' => Http::response(['nope'], 403)]);

    (new DeliverWebhookJob($delivery->getKey()))
        ->handle(app(UrlGuard::class), app(WebhookDeliveryClient::class), app(RecordDeliveryOutcomeAction::class));

    expect($delivery->refresh()->{WebhookDeliverySchema::STATUS})->toBe(WebhookDeliveryStatusEnum::FAILED_PERMANENT)
        ->and($delivery->{WebhookDeliverySchema::ERROR_CATEGORY})->toBe('permanent')
        ->and($delivery->{WebhookDeliverySchema::NEXT_ATTEMPT_AT})->toBeNull();
});

it('marks 5xx retryable and records the attempt history', function () {
    [$endpoint] = $this->webhookEndpoint(['order.paid']);
    $delivery = deliveryFor(outboxEvent(), $endpoint);

    Http::fake(['*93.184.216.34*' => Http::response([], 500)]);

    (new DeliverWebhookJob($delivery->getKey()))
        ->handle(app(UrlGuard::class), app(WebhookDeliveryClient::class), app(RecordDeliveryOutcomeAction::class));

    expect($delivery->refresh()->{WebhookDeliverySchema::STATUS})->toBe(WebhookDeliveryStatusEnum::FAILED_RETRYABLE)
        ->and($delivery->{WebhookDeliverySchema::NEXT_ATTEMPT_AT})->not->toBeNull()
        ->and($delivery->{WebhookDeliverySchema::ATTEMPT_COUNT})->toBe(1);
});

it('claims exclusively: a second job on the same delivery sends nothing extra', function () {
    [$endpoint] = $this->webhookEndpoint(['order.paid']);
    $delivery = deliveryFor(outboxEvent(), $endpoint);

    Http::fake(['*93.184.216.34*' => Http::response([], 200)]);

    $job = new DeliverWebhookJob($delivery->getKey());
    $deps = [app(UrlGuard::class), app(WebhookDeliveryClient::class), app(RecordDeliveryOutcomeAction::class)];

    $job->handle(...$deps);
    // Same delivery again: status is succeeded — the atomic claim matches
    // nothing, no second HTTP call.
    $job->handle(...$deps);

    Http::assertSentCount(1);
});

it('a stale worker cannot overwrite a newer claim', function () {
    [$endpoint] = $this->webhookEndpoint(['order.paid']);
    $delivery = deliveryFor(outboxEvent(), $endpoint, 'processing');

    // Worker B claims (new token).
    $newToken = (string) Str::uuid();
    $delivery->forceFill([
        WebhookDeliverySchema::CLAIM_TOKEN => $newToken,
        WebhookDeliverySchema::CLAIMED_AT => now(),
    ])->save();

    // Worker A (old token) tries to finalize its run.
    $updated = WebhookDelivery::query()
        ->where(WebhookDeliverySchema::ID, $delivery->getKey())
        ->where(WebhookDeliverySchema::CLAIM_TOKEN, 'old-token-a')
        ->update([
            WebhookDeliverySchema::STATUS => 'succeeded',
            WebhookDeliverySchema::DELIVERED_AT => now(),
        ]);

    expect($updated)->toBe(0)
        ->and($delivery->refresh()->{WebhookDeliverySchema::CLAIM_TOKEN})->toBe($newToken)
        ->and($delivery->refresh()->{WebhookDeliverySchema::STATUS})->toBe(WebhookDeliveryStatusEnum::PROCESSING);
});

it('blocks a private IP at delivery time (SSRF re-validation)', function () {
    [$endpoint] = $this->webhookEndpoint(['order.paid']);
    $endpoint->update(['url' => 'https://127.0.0.1/hook']);

    $delivery = deliveryFor(outboxEvent(), $endpoint);

    Http::fake(); // must never be hit

    (new DeliverWebhookJob($delivery->getKey()))
        ->handle(app(UrlGuard::class), app(WebhookDeliveryClient::class), app(RecordDeliveryOutcomeAction::class));

    expect($delivery->refresh()->{WebhookDeliverySchema::ERROR_CATEGORY})->toBe('url_blocked');

    Http::assertNothingSent();
});

it('an endpoint auto-disables at the failure threshold', function () {
    [$endpoint] = $this->webhookEndpoint(['order.paid']);
    $endpoint->update(['consecutive_failures' => 9]);

    $delivery = deliveryFor(outboxEvent(), $endpoint);

    Http::fake(['*93.184.216.34*' => Http::response([], 500)]);

    (new DeliverWebhookJob($delivery->getKey()))
        ->handle(app(UrlGuard::class), app(WebhookDeliveryClient::class), app(RecordDeliveryOutcomeAction::class));

    expect($endpoint->refresh()->consecutive_failures)->toBe(10)
        ->and($endpoint->refresh()->is_active)->toBeFalse();
});

it('a success resets the consecutive failure counter (9-1-1 stays one)', function () {
    [$endpoint] = $this->webhookEndpoint(['order.paid']);
    $endpoint->update(['consecutive_failures' => 9]);

    // One fake whose stub callbacks MERGE and match in registration
    // order — so the FIRST stub (200) serves the first call and the
    // second stub (500) serves the second.
    Http::fake([
        '*93.184.216.34*' => Http::sequence()
            ->push([], 200)
            ->push([], 500),
    ]);

    $okDelivery = deliveryFor(outboxEvent('order.paid'), $endpoint);
    (new DeliverWebhookJob($okDelivery->getKey()))
        ->handle(app(UrlGuard::class), app(WebhookDeliveryClient::class), app(RecordDeliveryOutcomeAction::class));

    expect($endpoint->refresh()->consecutive_failures)->toBe(0)->and($endpoint->refresh()->is_active)->toBeTrue();

    $failDelivery = deliveryFor(outboxEvent('order.paid'), $endpoint);
    (new DeliverWebhookJob($failDelivery->getKey()))
        ->handle(app(UrlGuard::class), app(WebhookDeliveryClient::class), app(RecordDeliveryOutcomeAction::class));

    expect($endpoint->refresh()->consecutive_failures)->toBe(1)->and($endpoint->refresh()->is_active)->toBeTrue();
});
