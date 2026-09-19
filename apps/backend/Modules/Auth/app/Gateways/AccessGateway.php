<?php

namespace Modules\Auth\Gateways;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
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

    public function adminUserIds(): array
    {
        return User::query()
            ->where(UserSchema::IS_ACTIVE, true)
            ->whereHas('roles')
            ->pluck(UserSchema::ID)
            ->map(fn (int $id): int => $id)
            ->all();
    }

    public function getUserEmailsByIds(array $ids): array
    {
        return User::query()
            ->whereIn(UserSchema::ID, $ids)
            ->pluck(UserSchema::EMAIL, UserSchema::ID)
            ->all();
    }
}
