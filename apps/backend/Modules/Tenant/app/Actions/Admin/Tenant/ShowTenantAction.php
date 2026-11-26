<?php

namespace Modules\Tenant\Actions\Admin\Tenant;

use Modules\Tenant\Models\Tenant;

class ShowTenantAction
{
    public function handle(string $id): Tenant
    {
        return Tenant::query()
            ->with('domains')
            ->whereKey($id)
            ->firstOrFail();
    }
}
