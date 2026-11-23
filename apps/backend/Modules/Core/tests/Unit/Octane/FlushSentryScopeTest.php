<?php

use App\Octane\FlushSentryScope;
use Laravel\Octane\Events\RequestReceived;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Scope;

/*
 * Regression for the octane isolation gate's Sentry-scope assertions. On
 * a persistent worker the enrichment provider writes the SDK-global
 * scope, and the Sentry integration's own per-request isolation is only
 * active when a DSN is configured — so one scope collected every
 * request's tags, user and breadcrumbs for the worker's whole lifetime.
 */

/**
 * The current scope, read back the only way Sentry 4 offers. The scope
 * object itself is returned and kept referenced: spl_object_id is no
 * identity test here (PHP recycles a handle as soon as the popped scope
 * is freed), a live reference is.
 *
 * @return array{tags: array<string, string>, breadcrumbs: int, scope: Scope}
 */
function flushedScopeState(): array
{
    $state = ['tags' => [], 'breadcrumbs' => 0, 'scope' => null];

    SentrySdk::getCurrentHub()->configureScope(function (Scope $scope) use (&$state): void {
        $event = $scope->applyToEvent(Event::createEvent());

        $state['tags'] = $event?->getTags() ?? [];
        $state['breadcrumbs'] = count($event?->getBreadcrumbs() ?? []);
        $state['scope'] = $scope;
    });

    return $state;
}

function flushForNextRequest(): void
{
    (new FlushSentryScope)->handle(new RequestReceived(app(), app(), request()));
}

function leaveRequestTracesOnTheCurrentScope(): void
{
    SentrySdk::getCurrentHub()->configureScope(function (Scope $scope): void {
        $scope->setTag('request_id', 'request-a');
        $scope->setTag('gate-marker', 'MARK-A');
        $scope->setUser(['id' => '7']);
        $scope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'request', 'GET /a'));
    });
}

it('starts every request from a clean scope, even after a write straight to the boot layer', function () {
    // Straight to the current scope with no flush in between — what a
    // boot-time or between-request write looks like.
    leaveRequestTracesOnTheCurrentScope();

    $previous = flushedScopeState();
    expect($previous['tags'])->toHaveKey('gate-marker')
        ->and($previous['breadcrumbs'])->toBe(1);

    flushForNextRequest();

    $next = flushedScopeState();

    expect($next['tags'])->toBe([])
        ->and($next['breadcrumbs'])->toBe(0)
        // A different object, not the previous one merely cleared: a
        // reference taken during a request can never see the next one.
        ->and($next['scope'])->not->toBe($previous['scope']);
});

it('leaves nothing of the previous request for the next one', function () {
    flushForNextRequest();
    leaveRequestTracesOnTheCurrentScope();
    $previous = flushedScopeState();

    flushForNextRequest();
    $next = flushedScopeState();

    expect($next['tags'])->toBe([])
        ->and($next['breadcrumbs'])->toBe(0)
        ->and($next['scope'])->not->toBe($previous['scope']);
});

it('keeps replacing the scope across many requests of the same worker', function () {
    flushForNextRequest();
    $scopes = [flushedScopeState()['scope']];

    for ($i = 0; $i < 5; $i++) {
        SentrySdk::getCurrentHub()->configureScope(fn (Scope $scope) => $scope->setTag('request_id', 'request-'.$i));
        flushForNextRequest();

        $state = flushedScopeState();
        expect($state['tags'])->toBe([]);
        $scopes[] = $state['scope'];
    }

    // Every request got a scope object of its own. All are kept alive
    // here, so their handles cannot have been recycled.
    $handles = array_map('spl_object_id', $scopes);

    expect(count($handles))->toBe(count(array_unique($handles)));
});

it('unwinds layers stranded by requests that never terminated', function () {
    flushForNextRequest();

    $hub = SentrySdk::getCurrentHub();

    // Three requests whose termination never popped their layer.
    for ($i = 0; $i < 3; $i++) {
        $hub->pushScope();
        $hub->configureScope(fn (Scope $scope) => $scope->setTag('stranded', (string) $i));
    }

    flushForNextRequest();

    // Clean again, and exactly one layer above the boot layer — the stack
    // does not grow with the worker's request count.
    expect(flushedScopeState()['tags'])->toBe([])
        ->and($hub->popScope())->toBeTrue()
        ->and($hub->popScope())->toBeFalse();
});
