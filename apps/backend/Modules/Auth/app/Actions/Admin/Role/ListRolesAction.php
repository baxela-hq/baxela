<?php

namespace Modules\Auth\Actions\Admin\Role;

use Illuminate\Database\Eloquent\Collection;
use Modules\Auth\Models\Role;

class ListRolesAction
{
    public function handle(): Collection
    {
        return Role::query()
            ->orderBy('id')
            ->get();
    }
}
