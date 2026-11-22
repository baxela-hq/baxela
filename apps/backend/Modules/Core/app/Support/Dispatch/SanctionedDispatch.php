<?php

namespace Modules\Core\Support\Dispatch;

use Illuminate\Support\Facades\Event;

/**
 * The sanctioned dispatch wrapper (postgres-migration §3.3.0a): the single
 * choke point for statically-undiscoverable dispatch — dynamic class
 * names, variable targets, wrapper helpers. Registered by construction
 * (every call site routes through this class, which the architecture
 * test can see), so variable-target dispatch stays reviewable without
 * claiming static discovery of mechanisms the test cannot inspect.
 *
 * Direct, statically-visible calls (`dispatch(new X)`, `event(new X)`,
 * listener maps, observers) do NOT need this wrapper — they are audited
 * via the registry itself.
 */
final class SanctionedDispatch
{
    /**
     * Dispatch a job whose class is resolved dynamically. The class must
     * be a registered ShouldQueue entry (see core.dispatch.queued) — the
     * registry is the durability contract, this method is only the
     * discoverability boundary.
     *
     * @template T
     *
     * @param  T  $job
     * @return T
     */
    public static function job(mixed $job): mixed
    {
        return dispatch($job);
    }

    /** Fire a domain event object resolved dynamically. */
    public static function event(object $event): void
    {
        Event::dispatch($event);
    }
}
