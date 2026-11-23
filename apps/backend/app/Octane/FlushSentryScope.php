<?php

namespace App\Octane;

use Laravel\Octane\Events\RequestReceived;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Throwable;

/**
 * Gives every request on a persistent Octane worker a clean Sentry scope
 * (fixes the isolation regression the octane gate flags): tags, user
 * context, and breadcrumbs must never bleed from one request into the
 * next, and the scope must be a NEW object each time so a reference taken
 * during one request can never observe another.
 *
 * The Sentry Laravel integration does this itself — but only when a DSN
 * is configured (it binds its Octane handlers in that case alone), while
 * our enrichment provider writes to the scope regardless. So without a
 * DSN one boot-layer scope collected every request's tags and
 * breadcrumbs for the worker's whole lifetime.
 *
 * The same hub object stays current throughout: the container and the
 * integration's log channel hold it as a singleton, so replacing the hub
 * would fork state between SDK-global and container-held consumers.
 *
 * Observability plumbing must never take a request down, hence the guard.
 */
class FlushSentryScope
{
    /**
     * Handle the event.
     *
     * @param  RequestReceived  $event
     */
    public function handle($event): void
    {
        try {
            $hub = SentrySdk::getCurrentHub();

            // Unwind to the hub's boot layer: the previous request's
            // layer, plus any a failed termination or the integration's
            // own per-request push left stranded.
            while ($hub->popScope()) {
            }

            // Make that layer pristine IN PLACE. It is the template every
            // later request is cloned from, so anything ever written to
            // it directly (boot time, a task between requests) would
            // otherwise be inherited by every request for good.
            $hub->configureScope(static function (Scope $scope): void {
                $scope->clear();
            });

            // The request works on its own layer — a distinct object.
            $hub->pushScope();
        } catch (Throwable) {
            // SDK not bootstrapped — nothing to reset, and never a reason
            // to fail the request.
        }
    }
}
