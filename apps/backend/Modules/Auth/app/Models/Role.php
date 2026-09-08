<?php

namespace Modules\Auth\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /** Canonical name of the role that bypasses every permission check (see Gate::before). */
    public const string SUPER_ADMIN = 'super-admin';

    protected $table = 'auth_roles';
}
