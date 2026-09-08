<?php

namespace Modules\Auth\Gateways;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class AccessGateway implements AccessGatewayInterface
{
    public function userCan(Authenticatable $user, string $permission): bool
    {
        try {
            return $user->can($permission);
        } catch (PermissionDoesNotExist) {
            // an unsynced admin route must fail closed, not blow up
            return false;
        }
    }
}
