<?php

namespace Modules\Auth\Actions\Admin\Permission;

use Illuminate\Database\Eloquent\Collection;
use Modules\Auth\Models\Permission;
use Modules\Auth\Schemas\Permission\PermissionSchema;

class ListPermissionsAction
{
    /** @return Collection<int, Permission> */
    public function handle(): Collection
    {
        return Permission::query()
            ->orderBy(PermissionSchema::NAME)
            ->get();
    }
}
