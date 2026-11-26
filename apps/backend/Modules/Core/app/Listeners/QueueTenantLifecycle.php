<?php

namespace Modules\Core\Listeners;

use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Modules\Core\Exceptions\CentralOperationException;
use Modules\Core\Support\Tenancy\CentralOperationRegistry;
use Modules\Core\Support\Tenancy\TenantContext;

/**
 * Queue lifecycle of the tenant context (multi-tenancy §6.1).
 *
 * JobProcessing pushes the state it found, then deterministically
 * initializes from the payload: a tenant id selects the store, a central
 * operation id grants NO mode (the job must invoke the registered
 * operation itself), and anything else is INACTIVE. JobAttempted — fired
 * in the worker's and the sync driver's `finally` after every attempt —
 * pops that snapshot, so nested synchronous jobs unwind in order and the
 * enclosing request keeps its own tenant.
 */
class QueueTenantLifecycle
{
    public function __construct(private readonly CentralOperationRegistry $registry) {}

    /**
     * @throws CentralOperationException
     */
    public function processing(JobProcessing $event): void
    {
        $context = app(TenantContext::class);
        $payload = $event->job->payload();

        $context->pushSnapshot();

        $operation = $payload['central_operation'] ?? null;

        if (is_string($operation) && $operation !== '') {
            $definition = $this->registry->definition($operation);
            $job = $event->job->resolveName();

            if (! in_array($job, $definition['jobs'], true)) {
                // Fail closed, before the job body runs.
                throw CentralOperationException::jobNotAllowed($operation, $job);
            }

            $context->deactivate();

            return;
        }

        $tenantId = $payload['tenant_id'] ?? null;

        if (is_int($tenantId)) {
            $context->forTenant($tenantId);

            return;
        }

        $context->deactivate();
    }

    public function attempted(JobAttempted $event): void
    {
        app(TenantContext::class)->popSnapshot();
    }
}
