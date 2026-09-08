<?php

namespace Modules\Auth\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $table = 'auth_permissions';
}
