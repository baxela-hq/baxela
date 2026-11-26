<?php

namespace Modules\Core\Contracts\Events\Tenant;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

/**
 * A user provisioned their own store through self-service onboarding
 * (multi-tenancy §6.5, Category 3). Creating a store changes the
 * platform, so the event is audited like an admin mutation.
 */
class TenantProvisionedEvent extends AbstractBaseEvent
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public int $owner_id,
    ) {}
}
