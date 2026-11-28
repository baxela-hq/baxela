<?php

namespace Modules\Setting\Listeners;

use Modules\Core\Support\Tenancy\TenantResolved;
use Modules\Setting\Support\AppliesStoreRuntime;

/**
 * Per-request store runtime (§6.7 items 1–2). Raised after ResolveTenant
 * has identified the store; applied again on every event, with no
 * once-only guard, so a settings change is visible on the next request.
 */
class ApplyTenantRuntime
{
    public function __construct(private readonly AppliesStoreRuntime $runtime) {}

    public function __invoke(TenantResolved $event): void
    {
        $this->runtime->apply($event->tenant->id);
    }
}
