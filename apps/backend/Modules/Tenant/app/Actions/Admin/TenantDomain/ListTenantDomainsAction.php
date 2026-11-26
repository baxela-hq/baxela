<?php

namespace Modules\Tenant\Actions\Admin\TenantDomain;

use Illuminate\Support\Collection;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantDomain;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;

class ListTenantDomainsAction
{
    /** @return Collection<int, TenantDomain> */
    public function handle(string $id): Collection
    {
        Tenant::query()->whereKey($id)->firstOrFail();

        return TenantDomain::query()
            ->where(TenantDomainSchema::TENANT_ID, $id)
            ->orderBy(TenantDomainSchema::ID)
            ->get();
    }
}
