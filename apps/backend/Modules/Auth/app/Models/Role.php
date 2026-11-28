<?php

namespace Modules\Auth\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /** Canonical name of the role that bypasses every permission check (see Gate::before). */
    public const string SUPER_ADMIN = 'super-admin';

    /** Canonical name of the per-tenant owner role seeded for every store (§8 Phase 3). */
    public const string TENANT_OWNER = 'owner';

    protected $table = 'auth_roles';
}
