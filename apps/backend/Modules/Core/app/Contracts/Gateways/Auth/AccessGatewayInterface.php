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

    /**
     * Whether the user is the platform super-admin — the NULL-team role
     * bypass (multi-tenancy §3.2/§5), read team-context-immune and
     * morph-disciplined. This is what lets the super-admin retain access
     * to a suspended store and switch stores by header.
     */
    public function isPlatformSuperAdmin(Authenticatable $user): bool;

    /**
     * Ids of active staff accounts — users holding any Spatie role
     * (customers sign up without a role). Backs fan-out of admin
     * database notifications.
     *
     * @return array<int, int>
     */
    public function adminUserIds(): array;

    /**
     * Email addresses for the given user ids (users are keyed by id;
     * unknown ids are absent from the result).
     *
     * @param  array<int, int|string>  $ids
     * @return array<int|string, string>
     */
    public function getUserEmailsByIds(array $ids): array;

    /**
     * User ids for the given email addresses, keyed by address; addresses
     * belonging to no user are absent. Backs per-user filtering of
     * email-channel notification recipients.
     *
     * @param  array<int, string>  $emails
     * @return array<string, int|string>
     */
    public function getUserIdsByEmails(array $emails): array;

    /**
     * Every local user id (C6: the User transfer exports a complete user
     * directory — identity lives in Auth, so the directory is Auth's to
     * enumerate).
     *
     * @return array<int, int>
     */
    public function listUserIds(): array;

    /**
     * The local user id for the given email, or — when no account exists —
     * a freshly created one that is INERT until explicitly activated or
     * verified: `is_active=false`, unverified email, a random unguessable
     * password, and no roles. The user-transfer import uses this to
     * restore identity for customers the destination store has never seen
     * (C6): the rows become attached and restorable, the accounts cannot
     * sign in until Auth's activation/verification flows run (enforced by
     * the token-issuance path's `is_active` re-check, not by the import).
     *
     * Emails are canonicalized (trim + lowercase) for both the lookup and
     * the create; a concurrent create of the same address converges on the
     * winner via the email unique index.
     */
    public function ensureUserByEmail(string $email): int;

    /**
     * Lock the user's row for the rest of the current transaction
     * (`SELECT … FOR UPDATE`), so two transactions about the same user
     * serialize — tenant onboarding checks its per-user quota under this
     * lock. False when no such user exists. Call inside a transaction;
     * SQLite takes no row lock.
     */
    public function lockUser(int $userId): bool;

    /**
     * Seed the default roles of one store (multi-tenancy §8 Phase 3): the
     * tenant-scoped `owner` role with the per-module wildcard permissions
     * a store admin needs — platform routes stay unreachable because no
     * permission row exists for them (§4.1). Idempotent; returns the
     * owner role's id.
     */
    public function seedTenantRoles(int $tenantId): int;

    /**
     * Assign one role to a user AS the given store (§6.5 Category 2): the
     * spatie team id is set to the tenant around the attach and restored
     * afterward, so the pivot row carries the store (§5). The role must
     * belong to the tenant — platform NULL-team roles are not assignable
     * here.
     */
    public function assignTenantRole(int $userId, int $tenantId, int $roleId): void;

    /**
     * Replace a member's role set within one store (§6.5 Category 2, role
     * assignment): the team-scoped sync touches only that store's pivot
     * rows — the member's roles in other stores are untouched. Every role
     * id must belong to the tenant.
     *
     * @param  array<int, int>  $roleIds
     */
    public function syncTenantMemberRoles(int $userId, int $tenantId, array $roleIds): void;
}
