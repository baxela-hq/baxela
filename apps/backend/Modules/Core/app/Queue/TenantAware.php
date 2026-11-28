<?php

namespace Modules\Core\Queue;

use Modules\Core\Support\Tenancy\TenantContext;

/**
 * Defense-in-depth queue middleware (multi-tenancy §6.1, opt-in): re-pins
 * the job's payload stamp on every attempt, whatever the lifecycle
 * listeners left behind, and restores the previous context in `finally`.
 * Used explicitly by jobs whose work must be row-authoritative for one
 * store (DeliverWebhookJob); a job without it relies on the lifecycle
 * alone.
 */
class TenantAware
{
    public function handle(\Illuminate\Contracts\Queue\Job $job, \Closure $next): void
    {
        $context = app(TenantContext::class);
        $previous = $context->mode() === TenantContext::MODE_TENANT ? $context->tenantId() : null;

        $payloadTenantId = $job->payload()['tenant_id'] ?? null;

        if (is_int($payloadTenantId) || is_string($payloadTenantId)) {
            $context->forTenant((int) $payloadTenantId);
        }

        try {
            $next($job);
        } finally {
            if ($previous === null) {
                $context->deactivate();
            } else {
                $context->forTenant($previous);
            }
        }
    }
}
