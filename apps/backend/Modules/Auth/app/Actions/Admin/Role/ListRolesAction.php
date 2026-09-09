<?php

namespace Modules\Auth\Actions\Admin\Role;

use Illuminate\Database\Eloquent\Collection;
use Modules\Auth\Models\Role;
use Modules\Auth\Schemas\Role\RoleSchema;

class ListRolesAction
{
    public function handle(): Collection
    {
        return Role::query()
            ->with(RoleSchema::PERMISSIONS)
            ->orderBy(RoleSchema::ID)
            ->get();
    }
}
