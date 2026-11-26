<?php

namespace Modules\Auth\Gateways;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
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

    public function getUserIdsByEmails(array $emails): array
    {
        return User::query()
            ->whereIn(UserSchema::EMAIL, $emails)
            ->pluck(UserSchema::ID, UserSchema::EMAIL)
            ->all();
    }

    public function listUserIds(): array
    {
        return User::query()
            ->pluck(UserSchema::ID)
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
    }

    public function ensureUserByEmail(string $email): int
    {
        $canonical = mb_strtolower(trim($email));

        // Case-insensitive lookup: accounts may predate canonicalization,
        // and identity must win over storage casing.
        $existing = User::query()
            ->whereRaw('LOWER('.UserSchema::EMAIL.') = ?', [$canonical])
            ->first();
        if ($existing !== null) {
            return (int) $existing->getKey();
        }

        try {
            $record = User::query()->create([
                UserSchema::EMAIL => $canonical,
                // Inert until activation/verification (C6): the sign-in
                // token path re-checks is_active under a row lock, so an
                // imported account cannot authenticate, and the random
                // password is unguessable — nobody can pass a credential
                // check on it.
                UserSchema::IS_ACTIVE => false,
                UserSchema::PASSWORD => Str::password(32),
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent import created the same address — adopt it.
            $record = User::query()
                ->where(UserSchema::EMAIL, $canonical)
                ->firstOrFail();
        }

        return (int) $record->getKey();
    }

    public function lockUser(int $userId): bool
    {
        return User::query()
            ->whereKey($userId)
            ->lockForUpdate()
            ->first([UserSchema::ID]) !== null;
    }
}
