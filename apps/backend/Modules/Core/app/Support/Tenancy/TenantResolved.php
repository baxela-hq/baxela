<?php

namespace Modules\Core\Support\Tenancy;

use Modules\Core\Contracts\Gateways\Tenant\DTOs\TenantSnapshotDto;

/**
 * Raised by ResolveTenant once identification has established a serving
 * tenant (multi-tenancy §3.1). Store-dependent consumers — the per-request
 * runtime, log context, Sentry tagging — listen for it. A Core contract:
 * modules subscribe, Core never reaches into them.
 */
final class TenantResolved
{
    public function __construct(
        public readonly TenantSnapshotDto $tenant,
        /** The route audience the store was resolved for (ResolveTenant::AUDIENCE_*). */
        public readonly string $audience,
    ) {}
}
