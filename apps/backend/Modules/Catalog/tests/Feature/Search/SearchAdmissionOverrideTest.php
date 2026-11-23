<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Audit\Models\AuditLog;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema as Coordination;
use Modules\Catalog\Search\SearchAdmissionOverride;
use Modules\Catalog\Search\SearchWorkerHeartbeat;

uses(RefreshDatabase::class);

/*
 * The operator override (postgres-migration §3.2.2, matrix 55–56) and the
 * heartbeat maintenance it lives beside. Admissions and heartbeat rows are
 * seeded directly: the gate only creates them for the CURRENT process.
 */

const SAO_DEAD = 'deadbeefdeadbeefdeadbeefdeadbeef';

const SAO_LIVE = '1111111122223333444455555555aaaa';

function saoAdmission(string $token, bool $open, int $pid = 4242): int
{
    return DB::table(Coordination::ADMISSIONS_TABLE)->insertGetId([
        Coordination::OWNER => 'worker:'.$pid,
        Coordination::INSTANCE_TOKEN => $token,
        Coordination::BOOT_ID => 'boot-1',
        Coordination::CONTAINER_ID => 'container-1',
        Coordination::CONTAINER_STARTED_AT => 8000,
        Coordination::PID => $pid,
        Coordination::PROCESS_START_TIME => 9000,
        Coordination::GENERATION => 1,
        Coordination::ACQUIRED_AT => now()->subMinutes(30),
        Coordination::RELEASED_AT => $open ? null : now()->subMinute(),
    ]);
}

/** @return list<int> the ids of the worker's open admissions */
function saoSeedWorker(string $token, int $open, int $released = 0): array
{
    DB::table(Coordination::WORKER_HEARTBEATS_TABLE)->insert([
        Coordination::INSTANCE_TOKEN => $token,
        Coordination::WORKER_OWNER => 'worker:'.$token,
        Coordination::INSTANCE_BOOT_ID => 'boot-1',
        Coordination::CONTAINER_ID => 'container-1',
        Coordination::PID => 4242,
        Coordination::PROCESS_START_TIME => 9000,
        Coordination::HEARTBEAT_AT => now()->subMinutes(30),
    ]);

    $ids = [];
    for ($i = 0; $i < $open; $i++) {
        $ids[] = saoAdmission($token, true);
    }
    for ($i = 0; $i < $released; $i++) {
        saoAdmission($token, false);
    }

    return $ids;
}

function saoOpenAdmissions(string $token): \Illuminate\Database\Query\Builder
{
    return DB::table(Coordination::ADMISSIONS_TABLE)
        ->where(Coordination::INSTANCE_TOKEN, $token)
        ->whereNull(Coordination::RELEASED_AT);
}

/**
 * The audit row of the latest override attempt.
 *
 * @return array<string, mixed>
 */
function saoAuditProperties(): array
{
    $row = AuditLog::query()
        ->where(AuditLogSchema::ENTITY, 'search_admission_override')
        ->latest('id')
        ->firstOrFail();

    return (array) $row->{AuditLogSchema::PROPERTIES};
}

it('releases exactly the open admissions of the named worker and audits it', function () {
    saoSeedWorker(SAO_DEAD, open: 2, released: 1);
    saoSeedWorker(SAO_LIVE, open: 1);

    $result = (new SearchAdmissionOverride)(SAO_DEAD, 'container removed by an oom kill', 'console:ops@host-1');

    expect($result->status)->toBe(SearchAdmissionOverride::RELEASED)
        ->and($result->released)->toBe(2)
        ->and(saoOpenAdmissions(SAO_DEAD)->count())->toBe(0)
        // The live worker's admission is not the override's to touch.
        ->and(saoOpenAdmissions(SAO_LIVE)->count())->toBe(1);

    $audit = saoAuditProperties();

    expect($audit['instance_token'])->toBe(SAO_DEAD) // recorded, never redacted
        ->and($audit['outcome'])->toBe(SearchAdmissionOverride::RELEASED)
        ->and($audit['released'])->toBe(2)
        ->and($audit['reason'])->toBe('container removed by an oom kill')
        ->and($audit['operator'])->toBe('console:ops@host-1')
        ->and($audit['occurred_at'])->toBeString()
        ->and($audit['pid'])->toBe(4242);
});

it('releases nothing for a stale token whose admissions were already reclaimed', function () {
    saoSeedWorker(SAO_DEAD, open: 0, released: 2);

    $result = (new SearchAdmissionOverride)(SAO_DEAD, 'drain stuck', 'console:ops@host-1');

    expect($result->status)->toBe(SearchAdmissionOverride::SUPERSEDED)
        ->and($result->released)->toBe(0)
        // The refused attempt is on the record too.
        ->and(saoAuditProperties()['outcome'])->toBe(SearchAdmissionOverride::SUPERSEDED);
});

it('refuses an unknown token and records the refusal', function () {
    $result = (new SearchAdmissionOverride)('ffffffffffffffffffffffffffffffff', 'drain stuck', 'console:ops@host-1');

    expect($result->status)->toBe(SearchAdmissionOverride::NOT_FOUND)
        ->and($result->released)->toBe(0)
        ->and(saoAuditProperties()['outcome'])->toBe(SearchAdmissionOverride::NOT_FOUND);
});

it('never releases an admission that is not the token\'s recorded incarnation', function () {
    saoSeedWorker(SAO_DEAD, open: 1);

    // Same token, different process incarnation — the "newer owner" the
    // operator's stale token must not reach.
    saoAdmission(SAO_DEAD, true, pid: 9999);

    $result = (new SearchAdmissionOverride)(SAO_DEAD, 'drain stuck', 'console:ops@host-1');

    expect($result->released)->toBe(1)
        ->and(saoOpenAdmissions(SAO_DEAD)->where(Coordination::PID, 9999)->count())->toBe(1);
});

it('rolls the release back when its audit row cannot be written', function () {
    $ids = saoSeedWorker(SAO_DEAD, open: 2);

    Schema::dropIfExists(AuditLogSchema::TABLE);

    expect(fn () => (new SearchAdmissionOverride)(SAO_DEAD, 'drain stuck', 'console:ops@host-1'))
        ->toThrow(QueryException::class);

    // An override never takes effect without its record.
    expect(saoOpenAdmissions(SAO_DEAD)->count())->toBe(2)
        ->and(saoOpenAdmissions(SAO_DEAD)->pluck('id')->all())->toEqual($ids);
});

// ---------------------------------------------------------------------------
// The command: mandatory reason, interactive confirmation, honest reporting.
// ---------------------------------------------------------------------------

it('refuses to run without a reason', function () {
    saoSeedWorker(SAO_DEAD, open: 1);

    $this->artisan('catalog:search:assume-dead', ['instance_token' => SAO_DEAD, '--reason' => '   '])
        ->expectsOutputToContain('A reason is mandatory')
        ->assertExitCode(1);

    expect(saoOpenAdmissions(SAO_DEAD)->count())->toBe(1);
});

it('refuses to release anything when run non-interactively', function () {
    saoSeedWorker(SAO_DEAD, open: 1);

    // A real console run: --no-interaction answers the confirmation with
    // its default, which is "no".
    $exit = Artisan::call('catalog:search:assume-dead', [
        'instance_token' => SAO_DEAD,
        '--reason' => 'drain stuck',
        '--no-interaction' => true,
    ]);

    expect($exit)->toBe(1)
        ->and(saoOpenAdmissions(SAO_DEAD)->count())->toBe(1);
});

it('releases on confirmation and reports the outcome', function () {
    saoSeedWorker(SAO_DEAD, open: 2);

    $this->artisan('catalog:search:assume-dead', ['instance_token' => SAO_DEAD, '--reason' => 'drain stuck'])
        // expectsConfirmation matches the question text exactly.
        ->expectsConfirmation(
            'Release the 2 open admission(s) of this worker? Only when you have verified it cannot be running — the release is recorded with your reason.',
            'yes',
        )
        ->expectsOutputToContain('Released 2 admission(s)')
        ->assertExitCode(0);

    expect(saoOpenAdmissions(SAO_DEAD)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Heartbeat maintenance (reconciler pass).
// ---------------------------------------------------------------------------

function saoHeartbeat(string $token, int $ageHours): void
{
    DB::table(Coordination::WORKER_HEARTBEATS_TABLE)->insert([
        Coordination::INSTANCE_TOKEN => $token,
        Coordination::WORKER_OWNER => 'worker:'.$token,
        Coordination::INSTANCE_BOOT_ID => 'boot-1',
        Coordination::CONTAINER_ID => 'container-1',
        Coordination::PID => 4242,
        Coordination::PROCESS_START_TIME => 9000,
        Coordination::HEARTBEAT_AT => now()->subHours($ageHours),
    ]);
}

it('prunes stale heartbeat rows that hold nothing open and keeps the rest', function () {
    saoHeartbeat('a-stale-idle', ageHours: 30);
    saoHeartbeat('b-stale-busy', ageHours: 30);
    saoAdmission('b-stale-busy', true);
    saoHeartbeat('c-fresh', ageHours: 1);

    // A stale row with an OPEN admission must stay: the drain's reclamation
    // only ever considers admissions joined to a heartbeat row.
    expect(SearchWorkerHeartbeat::prune())->toBe(1)
        ->and(DB::table(Coordination::WORKER_HEARTBEATS_TABLE)->orderBy(Coordination::INSTANCE_TOKEN)->pluck(Coordination::INSTANCE_TOKEN)->all())
        ->toEqual(['b-stale-busy', 'c-fresh']);
});

it('gives a worker whose row was pruned a new one on its next heartbeat upsert', function () {
    $heartbeats = DB::table(Coordination::WORKER_HEARTBEATS_TABLE);

    SearchWorkerHeartbeat::upsert();
    expect($heartbeats->count())->toBe(1);

    // The row ages out (30 hours without a report) while holding nothing.
    $heartbeats->update([Coordination::HEARTBEAT_AT => now()->subHours(30)]);
    expect(SearchWorkerHeartbeat::prune())->toBe(1)
        ->and($heartbeats->count())->toBe(0);

    SearchWorkerHeartbeat::upsert();

    expect($heartbeats->count())->toBe(1);
});
