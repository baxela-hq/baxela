<?php

namespace Modules\Core\Listeners;

use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Modules\Core\Contracts\Gateways\Tenant\StoreRuntimeInterface;
use Modules\Core\Exceptions\CentralOperationException;
use Modules\Core\Support\Tenancy\CentralOperationRegistry;
use Modules\Core\Support\Tenancy\TenantContext;

/**
 * Queue lifecycle of the tenant context and the store runtime
 * (multi-tenancy §6.1 + §6.7 item 3).
 *
 * JobProcessing pushes the state it found and captures the runtime
 * before validating the payload, so a rejected payload still has a
 * matching attempt snapshot to pop. It then deterministically initializes from the
 * payload: a tenant id selects the store and applies its runtime; a
 * central operation id grants NO mode (the job must invoke the
 * registered operation itself); anything else is INACTIVE. JobAttempted
 * — fired in the worker's and the sync driver's `finally` after every
 * attempt — pops the context snapshot first and then restores the
 * captured runtime into that previous context, so nested synchronous
 * jobs unwind in order and two stores served by one process never
 * share timezone/config/locale state.
 */
class QueueTenantLifecycle
{
    /** @var list<array<string, mixed>> */
    private array $runtimeStates = [];

    public function __construct(
        private readonly CentralOperationRegistry $registry,
        private readonly StoreRuntimeInterface $runtime,
    ) {}

    /**
     * @throws CentralOperationException
     */
    public function processing(JobProcessing $event): void
    {
        $context = app(TenantContext::class);
        $payload = $event->job->payload();

        // Pair every attempt with a snapshot before validation: the worker
        // fires JobAttempted even when this method throws, and an unpaired
        // pop would discard an outer job's state.
        $context->pushSnapshot();
        $this->runtimeStates[] = $this->runtime->capture();

        $operation = $payload['central_operation'] ?? null;

        if (is_string($operation) && $operation !== '') {
            $definition = $this->registry->definition($operation);
            $job = $event->job->resolveName();

            if (! in_array($job, $definition['jobs'], true)) {
                // Fail closed, before the job body runs.
                throw CentralOperationException::jobNotAllowed($operation, $job);
            }
        }

        if (is_string($operation) && $operation !== '') {
            $context->deactivate();

            return;
        }

        $tenantId = $payload['tenant_id'] ?? null;

        if (is_int($tenantId)) {
            $context->forTenant($tenantId);
            $this->runtime->apply($tenantId);

            return;
        }

        $context->deactivate();
    }

    public function attempted(JobAttempted $event): void
    {
        $state = array_pop($this->runtimeStates);

        try {
            // Pop first: tenant-keyed statics (SupportedLocales) must be
            // restored into the tenant that was active before the job.
            app(TenantContext::class)->popSnapshot();
        } finally {
            if ($state !== null) {
                $this->runtime->restore($state);
            }
        }
    }
}
