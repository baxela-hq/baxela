<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Contracts\Events\Order\OrderPaidEvent;
use Modules\Webhooks\Actions\Webhooks\ProcessOutboxEventAction;
use Modules\Webhooks\Actions\Webhooks\WriteOutboxEventAction;
use Modules\Webhooks\Jobs\DeliverWebhookJob;
use Modules\Webhooks\Listeners\RecordOrderPaidListener;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Models\WebhookEvent;
use Modules\Webhooks\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('writes one outbox row per unique transition and dedupes repeats', function () {
    $writer = app(WriteOutboxEventAction::class);

    $first = $writer->write('order.paid', 'order.paid:1024', ['order_code' => 'RC1']);
    $second = $writer->write('order.paid', 'order.paid:1024', ['order_code' => 'RC1']);

    expect($first->getKey())->toBe($second->getKey())
        ->and(WebhookEvent::query()->count())->toBe(1)
        ->and($first->payload['api_version'])->toBe('v1')
        ->and($first->payload['data']['order_code'])->toBe('RC1');
});

it('a listener writing inside a rolled-back transaction loses its outbox row', function () {
    // The atomicity contract: the insert joins the firing transaction.
    try {
        DB::transaction(function (): void {
            app(WriteOutboxEventAction::class)
                ->write('order.paid', 'order.paid:rollback-test', []);
            throw new RuntimeException('rollback now');
        });
    } catch (RuntimeException) {
    }

    expect(WebhookEvent::query()->where('source_key', 'order.paid:rollback-test')->exists())->toBeFalse();
});

it('processing creates deliveries only for subscribed active endpoints', function () {
    [$subscribed] = $this->webhookEndpoint(['order.paid']);
    [$unsubscribed] = $this->webhookEndpoint(['refund.processed']);
    [$inactive] = $this->webhookEndpoint(['order.paid'], ['is_active' => false]);

    $event = app(WriteOutboxEventAction::class)
        ->write('order.paid', 'order.paid:proc-test', []);

    $created = app(ProcessOutboxEventAction::class)->handle($event);

    expect($created)->toBe(1)
        ->and(WebhookDelivery::query()->count())->toBe(1)
        ->and(WebhookDelivery::query()->first()->endpoint_id)->toBe($subscribed->getKey())
        ->and($event->refresh()->processed_at)->not->toBeNull();
});

it('processing twice does not duplicate deliveries', function () {
    [$endpoint] = $this->webhookEndpoint(['order.paid']);
    $event = app(WriteOutboxEventAction::class)
        ->write('order.paid', 'order.paid:dedupe-test', []);

    $processor = app(ProcessOutboxEventAction::class);

    expect($processor->handle($event))->toBe(1)
        ->and($processor->handle($event->refresh()))->toBe(0)
        ->and(WebhookDelivery::query()->count())->toBe(1);
});

it('a paid order emits order.paid through the synchronous listener path', function () {
    Queue::fake();

    [$endpoint] = $this->webhookEndpoint(['order.paid']);

    $event = OrderPaidEvent::fill([
        'id' => 1024,
        'user_id' => 1,
        'order_code' => 'RC1024',
        'status' => 'processing',
        'payment_status' => 'paid',
        'total_amount' => 55.0,
    ]);

    (new RecordOrderPaidListener(
        app(WriteOutboxEventAction::class),
        app(ProcessOutboxEventAction::class),
    ))->handle($event);

    expect(WebhookEvent::query()->where('source_key', 'order.paid:1024')->count())->toBe(1)
        ->and(WebhookDelivery::query()->where('endpoint_id', $endpoint->getKey())->count())->toBe(1);

    // Fast-path enqueue happened.
    Queue::assertPushed(DeliverWebhookJob::class);
});

it('the sweep enqueues due deliveries', function () {
    [$endpoint] = $this->webhookEndpoint(['order.paid']);
    $event = app(WriteOutboxEventAction::class)
        ->write('order.paid', 'order.paid:sweep-test', []);
    app(ProcessOutboxEventAction::class)->handle($event);

    Queue::fake();

    $exit = Artisan::call('webhooks:dispatch-deliveries');

    expect($exit)->toBe(0)
        ->and(Queue::assertPushed(DeliverWebhookJob::class));
});
