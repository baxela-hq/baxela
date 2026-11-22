<?php

namespace Modules\Core\Support\Transfer;

use Illuminate\Database\Connection;

/**
 * The DB-clock expressions for the transfer lock lease (postgres-migration
 * §3.1.0): every expiry/validity decision is computed inside SQL, never in
 * PHP, so protocol correctness is immune to application-server clock skew.
 *
 * Per driver:
 * - PostgreSQL: `clock_timestamp()` — true wall clock DURING the
 *   transaction. Deliberately NOT `now()`: PG's now() is
 *   transaction_timestamp(), pinned at BEGIN, so a transaction that starts
 *   before a lease expires and evaluates after would misjudge a dead lease
 *   as live (review R4-F1).
 * - MySQL/MariaDB: `NOW(6)` — statement-start time, i.e. the execution
 *   moment for each single-statement predicate; equivalent to
 *   clock_timestamp() for this protocol's one-statement decisions.
 * - SQLite (test plane): strftime with the fixed
 *   '%Y-%m-%d %H:%M:%f' format — all protocol writes and comparisons use
 *   the same generator, so lexicographic text comparison is exact.
 */
final class TransferClock
{
    /** SQL expression for the current DB-clock instant. */
    public static function now(Connection $connection): string
    {
        return match ($connection->getDriverName()) {
            'pgsql' => 'clock_timestamp()',
            'mysql', 'mariadb' => 'NOW(6)',
            'sqlite' => "strftime('%Y-%m-%d %H:%M:%f','now')",
            default => 'CURRENT_TIMESTAMP',
        };
    }

    /** SQL expression for the DB-clock instant plus `$seconds`. */
    public static function nowPlus(Connection $connection, int $seconds): string
    {
        $seconds = max(0, $seconds);

        return match ($connection->getDriverName()) {
            'pgsql' => "clock_timestamp() + interval '{$seconds} seconds'",
            'mysql', 'mariadb' => "NOW(6) + INTERVAL {$seconds} SECOND",
            'sqlite' => "strftime('%Y-%m-%d %H:%M:%f','now','+{$seconds} seconds')",
            default => "CURRENT_TIMESTAMP + interval '{$seconds} seconds'",
        };
    }
}
