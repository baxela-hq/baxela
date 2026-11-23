<?php

namespace Modules\Catalog\Search;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;
use Modules\Core\Contracts\Events\Catalog\SearchAdmissionOverrideEvent;

/**
 * Operator override for admissions whose owning worker cannot be PROVEN
 * dead (postgres-migration §3.2.2). Reclamation normally needs a stale
 * heartbeat AND /proc incarnation evidence; a worker in another
 * container, or on a host whose /proc is unreadable, can never supply
 * that evidence, and its open admission blocks every cutover's drain.
 * This is the audited last resort for exactly that situation.
 *
 * Guarantees:
 *
 *  - The release is a CONDITIONAL mutation: only admissions that are
 *    still open AND belong to the token's recorded process incarnation
 *    are touched. A token is random per process and never re-issued, and
 *    the incarnation tuple (boot id, container, pid, start time) makes
 *    that a checked property — an operator supplying an old token can
 *    never release a newer owner's admissions.
 *  - The release and its audit record commit in ONE transaction: an
 *    override can never be unaudited, and a refused attempt (nothing
 *    open, unknown token) is audited too.
 *  - Fail closed: a token without a heartbeat row has no verifiable
 *    incarnation, so nothing is released.
 */
final class SearchAdmissionOverride
{
    public const string RELEASED = 'released';

    public const string SUPERSEDED = 'superseded';

    public const string NOT_FOUND = 'not-found';

    /**
     * @param  string  $reason    mandatory, free text — recorded with the audit row
     * @param  string  $operator  who is acting (console user@host, or an admin identity)
     */
    public function __invoke(string $instanceToken, string $reason, string $operator): SearchAdmissionOverrideResult
    {
        /** @var array{0: string, 1: int} $outcome */
        $outcome = DB::transaction(function () use ($instanceToken, $reason, $operator): array {
            $heartbeat = $this->heartbeat($instanceToken);

            $released = $heartbeat === null
                ? 0
                : $this->openAdmissions($instanceToken, $heartbeat)
                    ->update([SearchCoordinationSchema::RELEASED_AT => now()]);

            $status = match (true) {
                $heartbeat === null => self::NOT_FOUND,
                $released > 0 => self::RELEASED,
                default => self::SUPERSEDED,
            };

            event(new SearchAdmissionOverrideEvent(
                instance_token: $instanceToken,
                outcome: $status,
                released: $released,
                reason: $reason,
                operator: $operator,
                boot_id: $heartbeat === null ? null : (string) $heartbeat->{SearchCoordinationSchema::INSTANCE_BOOT_ID},
                pid: $heartbeat === null ? null : (int) $heartbeat->{SearchCoordinationSchema::PID},
                process_start_time: $heartbeat === null ? null : (string) $heartbeat->{SearchCoordinationSchema::PROCESS_START_TIME},
                occurred_at: now()->toDateTimeString(),
            ));

            return [$status, $released];
        });

        Log::warning('search admission override', [
            'instance_token' => $instanceToken,
            'outcome' => $outcome[0],
            'released' => $outcome[1],
            'reason' => $reason,
            'operator' => $operator,
        ]);

        return new SearchAdmissionOverrideResult($outcome[0], $outcome[1], $this->heartbeat($instanceToken));
    }

    /** The worker's heartbeat row, or null when no worker carries the token. */
    public function heartbeat(string $instanceToken): ?object
    {
        return DB::table(SearchCoordinationSchema::WORKER_HEARTBEATS_TABLE)
            ->where(SearchCoordinationSchema::INSTANCE_TOKEN, $instanceToken)
            ->first();
    }

    /**
     * Ids of the token's open admissions; null when the token is unknown.
     *
     * @return list<int>|null
     */
    public function openAdmissionIds(string $instanceToken): ?array
    {
        $heartbeat = $this->heartbeat($instanceToken);

        if ($heartbeat === null) {
            return null;
        }

        return $this->openAdmissions($instanceToken, $heartbeat)->pluck('id')->all();
    }

    /**
     * Open admissions of exactly the process incarnation recorded on the
     * heartbeat row (the "generation" guard of the override).
     */
    private function openAdmissions(string $instanceToken, object $heartbeat): Builder
    {
        $admissions = SearchCoordinationSchema::ADMISSIONS_TABLE;

        return DB::table($admissions)
            ->where(SearchCoordinationSchema::INSTANCE_TOKEN, $instanceToken)
            ->whereNull(SearchCoordinationSchema::RELEASED_AT)
            ->where($admissions.'.'.SearchCoordinationSchema::BOOT_ID, (string) $heartbeat->{SearchCoordinationSchema::INSTANCE_BOOT_ID})
            ->where($admissions.'.'.SearchCoordinationSchema::CONTAINER_ID, (string) $heartbeat->{SearchCoordinationSchema::CONTAINER_ID})
            ->where($admissions.'.'.SearchCoordinationSchema::PID, (int) $heartbeat->{SearchCoordinationSchema::PID})
            ->where($admissions.'.'.SearchCoordinationSchema::PROCESS_START_TIME, (int) $heartbeat->{SearchCoordinationSchema::PROCESS_START_TIME});
    }
}
