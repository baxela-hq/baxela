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
}
