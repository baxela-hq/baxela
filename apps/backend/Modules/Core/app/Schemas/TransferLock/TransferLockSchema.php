<?php

namespace Modules\Core\Schemas\TransferLock;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

/**
 * The destination-module import lock table (postgres-migration §3.1):
 * replaces MySQL named locks (GET_LOCK/IS_USED_LOCK/RELEASE_LOCK) with a
 * driver-agnostic lease row — owner token, fencing generation, DB-clock
 * expiry. Every expiry/validity decision uses the database clock (see
 * TransferClock), never PHP time.
 */
class TransferLockSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = 'core_transfer_locks';

    public const string NAME = 'name';

    public const string OWNER_TOKEN = 'owner_token';

    public const string GENERATION = 'generation';

    public const string HEARTBEAT_AT = 'heartbeat_at';

    public const string EXPIRES_AT = 'expires_at';
}
