<?php

namespace Modules\Payment\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\ConfigurableDriver;
use Modules\Core\Contracts\Gateways\Payment\PaymentCredentialResolverInterface;
use Modules\Payment\Gateways\PaymentDriverManager;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes database-stored credentials effective for every payment client
 * and driver without touching a single one of them: on every request,
 * each registered configurable driver's declared credential keys are
 * resolved through the resolver and written into config — including an
 * explicit null when no effective credential exists.
 *
 * The full overwrite (not apply-when-present) is what makes this safe
 * under persistent Octane workers: the config repository survives
 * between requests, so an apply-only merge would keep serving a
 * credential whose database row was deleted (review F1). Rewriting every
 * declared slot — env fallback read from the resolver's immutable
 * baseline — makes the worker's credential state deterministic per
 * request, and an undecryptable stored value lands as null, never as a
 * live env credential (review F2, fail-closed).
 *
 * This middleware is the SOLE writer of payment.<driver>.<credentialKey>
 * configuration: no other component may mutate those keys before this
 * middleware runs in a process, or the resolver's env baseline (which
 * initializes on this middleware's first resolver call, before any
 * write) could be contaminated. It runs prepended in the api group, so
 * admin, user and webhook surfaces all see the same credential state.
 */
class ApplyDatabaseCredentials
{
    public function __construct(
        protected PaymentDriverManager $manager,
        protected PaymentCredentialResolverInterface $resolver,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach (array_keys((array) config('payment.drivers')) as $name) {
            // A driver whose credential declaration cannot even be
            // resolved (a broken extension, a partially-mocked driver in
            // tests) keeps its env-backed config untouched — credential
            // application never takes a request down, mirroring the
            // extension boot guard's fail-closed-but-never-fatal stance.
            try {
                $driver = $this->manager->forName($name);

                if (! $driver instanceof ConfigurableDriver) {
                    continue;
                }

                $keys = $driver->credentialKeys();
            } catch (Throwable) {
                continue;
            }

            foreach ($keys as $key) {
                config([
                    "payment.{$name}.{$key}" => $this->resolver->credential($name, $key),
                ]);
            }
        }

        return $next($request);
    }
}
