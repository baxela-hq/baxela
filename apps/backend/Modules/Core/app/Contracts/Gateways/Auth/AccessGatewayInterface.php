<?php

namespace Modules\Core\Contracts\Gateways\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

interface AccessGatewayInterface
{
    /**
     * Whether the given user holds the permission (Laravel Gate check,
     * including role-inherited and wildcard permissions).
     */
    public function userCan(Authenticatable $user, string $permission): bool;
}
