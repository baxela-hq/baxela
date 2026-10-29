<?php

namespace Modules\Core\Tests\Feature\Sentry;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Exceptions\BaseException;
use Modules\Core\Http\Middleware\AssignRequestId;
use Sentry\ClientBuilder;
use Sentry\Laravel\Integration as SentryIntegration;
use Sentry\Options;
use Sentry\SentrySdk;

beforeEach(function () {
    // Swap the hub's client for one carrying a recording transport: events
    // flow through the real integration path (the reportable registered in
    // bootstrap/app.php) but never leave the process. The dummy DSN only
    // makes the client "enabled" so events are produced at all.
    $this->transport = new RecordingTransport;

    $builder = new ClientBuilder(new Options([
        'dsn' => 'http://public@test.ingest.sentry.io/1',
        'integrations' => [new SentryIntegration],
    ]));
    $builder->setTransport($this->transport);

    SentrySdk::getCurrentHub()->bindClient($builder->getClient());
});

it('keeps expected 4xx domain exceptions out of Sentry', function () {
    report(new BaseException('auth.invalid_credentials', 401));

    expect($this->transport->events)->toBeEmpty();
});

it('reports 5xx domain failures but not 4xx ones', function () {
    report(new BaseException('payment.gateway_failed', 502));
    report(new BaseException('auth.invalid_otp', 422));

    expect($this->transport->events)->toHaveCount(1)
        ->and($this->transport->events[0]->getExceptions()[0]->getValue())
        ->toBe('payment.gateway_failed');
});

it('reports unexpected exceptions to Sentry exactly once', function () {
    report(new \RuntimeException('exploded'));

    expect($this->transport->events)->toHaveCount(1)
        ->and($this->transport->events[0]->getExceptions()[0]->getValue())
        ->toBe('exploded');
});

it('tags each request events with that request id only', function () {
    $middleware = new AssignRequestId;

    // Two sequential "requests" in one process: the tag applied by the
    // middleware must match the ID returned to that request's client and
    // must not leak from the previous request.
    $first = $middleware->handle(Request::create('/api/v1/test'), fn () => new Response('ok'));
    SentrySdk::getCurrentHub()->captureException(new \RuntimeException('first'));

    $second = $middleware->handle(Request::create('/api/v1/test'), fn () => new Response('ok'));
    SentrySdk::getCurrentHub()->captureException(new \RuntimeException('second'));

    [$firstEvent, $secondEvent] = $this->transport->events;

    expect($firstEvent->getTags()['request_id'])->toBe($first->headers->get('X-REQUEST-ID'))
        ->and($secondEvent->getTags()['request_id'])->toBe($second->headers->get('X-REQUEST-ID'))
        ->and($secondEvent->getTags()['request_id'])->not->toBe($firstEvent->getTags()['request_id']);
});
