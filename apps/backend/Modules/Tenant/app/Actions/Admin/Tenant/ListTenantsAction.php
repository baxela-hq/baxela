<?php

namespace Modules\Tenant\Actions\Admin\Tenant;

use Illuminate\Support\Collection;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Schemas\Tenant\TenantSchema;

/**
 * Lists every store — the platform super-admin's tenant directory. The
 * tenants table is tenancy metadata (not store-owned data), so this is a
 * platform-directory read on a super-admin-only route, not a cross-store
 * read of store data; it does not enter central mode.
 */
class ListTenantsAction
{
    /** @return Collection<int, Tenant> */
    public function handle(): Collection
    {
        return Tenant::query()
            ->with('domains')
            ->orderBy(TenantSchema::ID)
            ->get();
    }
}
