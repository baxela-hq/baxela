<?php

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Auth\Models\User;
use Modules\Core\Exceptions\BaseException;
use Modules\Core\Http\Middleware\IdempotencyMiddleware;
use Modules\Core\Models\IdempotencyKey;
use Modules\Core\Schemas\IdempotencyKeys\IdempotencyKeysSchema;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Core\Tests\Feature\Http\Middleware\IdempotencySideEffectCounter;

/**
 * Cross-connection proofs for §6.6. RefreshDatabase wraps every test in a
 * transaction, so a second connection could never see the claim the
 * request just committed; DatabaseMigrations gives the two connections real
 * committed rows. PostgreSQL-gated — SQLite has no row locks or ON CONFLICT.
 */
uses(DatabaseMigrations::class);

beforeEach(function () {
    IdempotencySideEffectCounter::$executions = 0;

    Route::post('_testing/idempotent-write', function (Request $request) {
        IdempotencySideEffectCounter::$executions++;

        return response()->json(['data' => ['created' => true]]);
    })->middleware(IdempotencyMiddleware::class);

    config(['database.connections.idempotency_second' => config('database.connections.'.config('database.default'))]);
    DB::purge('idempotency_second');
});

function secondIdempotencyConnection(): Connection
{
    /** @var Connection $connection */
    $connection = DB::connection('idempotency_second');

    return $connection;
}

function awaitIdempotencyFile(string $path, int $timeoutSeconds = 15): void
{
    $deadline = microtime(true) + $timeoutSeconds;

    while (! file_exists($path)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException("Timed out waiting for {$path}.");
        }

        usleep(10_000);
    }
}

/**
 * @return array<string, mixed>
 */
function readIdempotencyOutcome(string $path): array
{
    awaitIdempotencyFile($path);
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Could not read {$path}.");
    }

    $decoded = json_decode($contents, true);

    if (! is_array($decoded)) {
        throw new RuntimeException("Invalid outcome in {$path}.");
    }

    /** @var array<string, mixed> $decoded */
    return $decoded;
}

/**
 * Fork a request whose database connection is created ONLY in the child, so
 * the two claimants are genuinely independent PostgreSQL sessions.
 *
 * @param  array{key: string, user_id: int, owner: string, out: string, ready?: ?string, barrier?: ?string, started?: ?string, resume?: ?string, exec_log?: ?string}  $options
 */
function forkIdempotencyRequest(array $options): int
{
    $pid = pcntl_fork();

    if ($pid === -1) {
        throw new RuntimeException('Could not fork an idempotency claimant.');
    }

    if ($pid !== 0) {
        return $pid;
    }

    try {
        DB::purge();
        DB::purge('idempotency_second');
        DB::connection()->reconnect();

        /** @var User $user */
        $user = User::query()->findOrFail($options['user_id']);
        app(TenantContext::class)->forTenant(1);

        if (isset($options['ready'])) {
            file_put_contents($options['ready'], '1');
        }

        if (isset($options['barrier'])) {
            awaitIdempotencyFile($options['barrier']);
        }

        $request = Request::create('/_testing/idempotent-write', 'POST', [], [], [], [
            'HTTP_X-IDEMPOTENCY-KEY' => $options['key'],
        ]);
        $request->setUserResolver(static fn () => $user);

        $response = app(IdempotencyMiddleware::class)->handle($request, static function () use ($options) {
            IdempotencySideEffectCounter::$executions++;

            if (isset($options['exec_log'])) {
                file_put_contents($options['exec_log'], $options['owner']."\n", FILE_APPEND);
            }

            if (isset($options['started'])) {
                file_put_contents($options['started'], '1');
            }

            if (isset($options['resume'])) {
                awaitIdempotencyFile($options['resume']);
            }

            return response()->json(['data' => ['owner' => $options['owner'], 'created' => true]]);
        });

        file_put_contents($options['out'], json_encode([
            'status' => $response->getStatusCode(),
            'replay' => $response->headers->get(IdempotencyMiddleware::IDEMPOTENCY_RESPONSE_KEY),
            'body' => $response->getContent(),
        ], JSON_THROW_ON_ERROR));
    } catch (BaseException $e) {
        file_put_contents($options['out'], json_encode([
            'error' => true,
            'code' => $e->code,
        ], JSON_THROW_ON_ERROR));
    } catch (Throwable $e) {
        file_put_contents($options['out'], json_encode([
            'error' => true,
            'message' => $e->getMessage(),
        ], JSON_THROW_ON_ERROR));
    }

    exit(0);
}

function waitIdempotencyChildren(int ...$pids): void
{
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }
}

it('runs one same-key request and replays its committed row from a second connection', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL-only plane check.');
    }

    $user = User::factory()->create();
    $this->actingAs($user);

    $second = secondIdempotencyConnection();
    expect($second->getPdo())->not->toBe(DB::connection()->getPdo());

    $headers = ['X-Idempotency-Key' => 'one-execution'];
    $this->postJson('_testing/idempotent-write', [], $headers)->assertOk();
    $this->postJson('_testing/idempotent-write', [], $headers)
        ->assertOk()
        ->assertHeader(IdempotencyMiddleware::IDEMPOTENCY_RESPONSE_KEY, 'true');

    $fromSecond = IdempotencyKey::on('idempotency_second')
        ->where(IdempotencyKeysSchema::KEY, 'one-execution')
        ->firstOrFail();

    expect(IdempotencySideEffectCounter::$executions)->toBe(1)
        ->and($fromSecond->{IdempotencyKeysSchema::STATUS})->toBe(IdempotencyKeysSchema::STATUS_COMPLETED)
        ->and($fromSecond->{IdempotencyKeysSchema::RESPONSE})->toBe(['data' => ['created' => true]]);
});

it('reclaims an expired claim written by another connection, then fences the second caller', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL-only row-lock plane check.');
    }

    $user = User::factory()->create();
    $this->actingAs($user);

    $second = secondIdempotencyConnection();
    expect($second->getPdo())->not->toBe(DB::connection()->getPdo());

    $oldToken = 'old-owner-token';
    IdempotencyKey::on('idempotency_second')->insert([
        'tenant_id' => 1,
        IdempotencyKeysSchema::USER_ID => $user->id,
        IdempotencyKeysSchema::PRINCIPAL_KEY => 'user:'.$user->id,
        IdempotencyKeysSchema::KEY => 'expired-key',
        IdempotencyKeysSchema::STATUS => IdempotencyKeysSchema::STATUS_PROCESSING,
        IdempotencyKeysSchema::CLAIM_TOKEN => $oldToken,
        IdempotencyKeysSchema::EXPIRES_AT => now()->subMinute(),
        IdempotencyKeysSchema::CREATED_AT => now()->subMinutes(2),
    ]);

    $this->postJson('_testing/idempotent-write', [], ['X-Idempotency-Key' => 'expired-key'])->assertOk();

    $reclaimed = IdempotencyKey::on('idempotency_second')
        ->where(IdempotencyKeysSchema::KEY, 'expired-key')
        ->firstOrFail();

    expect($reclaimed->{IdempotencyKeysSchema::CLAIM_TOKEN})->not->toBe($oldToken)
        ->and($reclaimed->{IdempotencyKeysSchema::STATUS})->toBe(IdempotencyKeysSchema::STATUS_COMPLETED)
        ->and(IdempotencySideEffectCounter::$executions)->toBe(1);

    // A second reclaimer sees the new unexpired claim: replay, never a
    // second execution and never another reclaim.
    $this->postJson('_testing/idempotent-write', [], ['X-Idempotency-Key' => 'expired-key'])
        ->assertOk()
        ->assertHeader(IdempotencyMiddleware::IDEMPOTENCY_RESPONSE_KEY, 'true');

    expect(IdempotencySideEffectCounter::$executions)->toBe(1)
        ->and(IdempotencyKey::on('idempotency_second')->where(IdempotencyKeysSchema::KEY, 'expired-key')->count())->toBe(1);
});

it('keeps guest claims distinct by cart token on the PostgreSQL plane', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL-only plane check.');
    }

    $tokenA = (string) Str::uuid();
    $tokenB = (string) Str::uuid();

    $this->postJson('_testing/idempotent-write', [], [
        'X-Idempotency-Key' => 'guest-key',
        'X-Cart-Token' => $tokenA,
    ])->assertOk();
    $this->postJson('_testing/idempotent-write', [], [
        'X-Idempotency-Key' => 'guest-key',
        'X-Cart-Token' => $tokenB,
    ])->assertOk();

    expect(IdempotencySideEffectCounter::$executions)->toBe(2)
        ->and(IdempotencyKey::count())->toBe(2)
        ->and(IdempotencyKey::query()->whereNull(IdempotencyKeysSchema::PRINCIPAL_KEY)->count())->toBe(0);
});

it('executes exactly one of two parallel same-key requests', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL-only parallel-plane check.');
    }

    $dir = sys_get_temp_dir().'/idempotency-parallel-'.Str::uuid()->toString();
    File::makeDirectory($dir, 0777, true);
    $userId = User::factory()->create()->id;

    $readyA = $dir.'/ready-a';
    $readyB = $dir.'/ready-b';
    $barrier = $dir.'/barrier';
    $startedA = $dir.'/started-a';
    $startedB = $dir.'/started-b';
    $resume = $dir.'/resume';
    $execLog = $dir.'/exec.log';
    $outA = $dir.'/a.json';
    $outB = $dir.'/b.json';
    $pids = [];
    file_put_contents($execLog, '');

    try {
        $pids[] = forkIdempotencyRequest([
            'key' => 'parallel-key',
            'user_id' => $userId,
            'owner' => 'A',
            'out' => $outA,
            'ready' => $readyA,
            'barrier' => $barrier,
            'started' => $startedA,
            'resume' => $resume,
            'exec_log' => $execLog,
        ]);
        $pids[] = forkIdempotencyRequest([
            'key' => 'parallel-key',
            'user_id' => $userId,
            'owner' => 'B',
            'out' => $outB,
            'ready' => $readyB,
            'barrier' => $barrier,
            'started' => $startedB,
            'resume' => $resume,
            'exec_log' => $execLog,
        ]);

        awaitIdempotencyFile($readyA);
        awaitIdempotencyFile($readyB);
        file_put_contents($barrier, 'go');

        $deadline = microtime(true) + 15;

        while (! file_exists($startedA) && ! file_exists($startedB)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Neither claimant entered the critical section.');
            }

            usleep(10_000);
        }

        file_put_contents($resume, 'go');
        waitIdempotencyChildren(...$pids);
        $pids = [];

        $outcomes = [readIdempotencyOutcome($outA), readIdempotencyOutcome($outB)];
        $wins = collect($outcomes)
            ->filter(static fn (array $outcome): bool => ($outcome['status'] ?? null) === 200)
            ->count();
        $refusals = collect($outcomes)
            ->filter(static fn (array $outcome): bool => ($outcome['code'] ?? null) === 'core.idempotency.in_progress')
            ->count();
        $executed = array_values(array_filter(explode("\n", (string) file_get_contents($execLog))));
        $row = IdempotencyKey::on('idempotency_second')
            ->where(IdempotencyKeysSchema::KEY, 'parallel-key')
            ->firstOrFail();

        expect($executed)->toHaveCount(1)
            ->and($wins)->toBeGreaterThanOrEqual(1)
            ->and($wins + $refusals)->toBe(2)
            ->and(IdempotencyKey::on('idempotency_second')->where(IdempotencyKeysSchema::KEY, 'parallel-key')->count())->toBe(1)
            ->and($row->{IdempotencyKeysSchema::STATUS})->toBe(IdempotencyKeysSchema::STATUS_COMPLETED)
            ->and($row->{IdempotencyKeysSchema::RESPONSE})->toBe(['data' => ['owner' => $executed[0], 'created' => true]]);
    } finally {
        if ($pids !== []) {
            if (! file_exists($resume)) {
                file_put_contents($resume, 'go');
            }

            waitIdempotencyChildren(...$pids);
        }

        File::deleteDirectory($dir);
    }
});

it('lets exactly one of two concurrent reclaimers win an expired claim', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL-only concurrent-reclaim check.');
    }

    $dir = sys_get_temp_dir().'/idempotency-reclaim-'.Str::uuid()->toString();
    File::makeDirectory($dir, 0777, true);
    $userId = User::factory()->create()->id;

    IdempotencyKey::on('idempotency_second')->insert([
        'tenant_id' => 1,
        IdempotencyKeysSchema::USER_ID => $userId,
        IdempotencyKeysSchema::PRINCIPAL_KEY => 'user:'.$userId,
        IdempotencyKeysSchema::KEY => 'reclaim-race',
        IdempotencyKeysSchema::STATUS => IdempotencyKeysSchema::STATUS_PROCESSING,
        IdempotencyKeysSchema::CLAIM_TOKEN => 'old-owner-token',
        IdempotencyKeysSchema::EXPIRES_AT => now()->subMinute(),
        IdempotencyKeysSchema::CREATED_AT => now()->subMinutes(2),
    ]);

    $readyA = $dir.'/ready-a';
    $readyB = $dir.'/ready-b';
    $barrier = $dir.'/barrier';
    $startedA = $dir.'/started-a';
    $startedB = $dir.'/started-b';
    $resume = $dir.'/resume';
    $outA = $dir.'/a.json';
    $outB = $dir.'/b.json';
    $pids = [];

    try {
        $pids[] = forkIdempotencyRequest([
            'key' => 'reclaim-race',
            'user_id' => $userId,
            'owner' => 'A',
            'out' => $outA,
            'ready' => $readyA,
            'barrier' => $barrier,
            'started' => $startedA,
            'resume' => $resume,
        ]);
        $pids[] = forkIdempotencyRequest([
            'key' => 'reclaim-race',
            'user_id' => $userId,
            'owner' => 'B',
            'out' => $outB,
            'ready' => $readyB,
            'barrier' => $barrier,
            'started' => $startedB,
            'resume' => $resume,
        ]);

        awaitIdempotencyFile($readyA);
        awaitIdempotencyFile($readyB);
        file_put_contents($barrier, 'go');

        $winnerStarted = null;
        $deadline = microtime(true) + 15;

        while ($winnerStarted === null) {
            foreach ([$startedA, $startedB] as $candidate) {
                if (file_exists($candidate)) {
                    $winnerStarted = $candidate;
                    break;
                }
            }

            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Neither reclaimer acquired the claim.');
            }

            usleep(10_000);
        }

        file_put_contents($resume, 'go');
        waitIdempotencyChildren(...$pids);
        $pids = [];

        $outcomes = [readIdempotencyOutcome($outA), readIdempotencyOutcome($outB)];
        $refusals = collect($outcomes)
            ->filter(static fn (array $outcome): bool => ($outcome['code'] ?? null) === 'core.idempotency.in_progress')
            ->count();
        $wins = collect($outcomes)
            ->filter(static fn (array $outcome): bool => ($outcome['status'] ?? null) === 200)
            ->count();
        $row = IdempotencyKey::on('idempotency_second')
            ->where(IdempotencyKeysSchema::KEY, 'reclaim-race')
            ->firstOrFail();

        expect($refusals)->toBe(1)
            ->and($wins)->toBe(1)
            ->and($row->{IdempotencyKeysSchema::CLAIM_TOKEN})->not->toBe('old-owner-token')
            ->and($row->{IdempotencyKeysSchema::STATUS})->toBe(IdempotencyKeysSchema::STATUS_COMPLETED);
    } finally {
        if ($pids !== []) {
            if (! file_exists($resume)) {
                file_put_contents($resume, 'go');
            }

            waitIdempotencyChildren(...$pids);
        }

        File::deleteDirectory($dir);
    }
});

it('fences the original request when a second connection reclaims its expired claim', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL-only fenced-completion check.');
    }

    $dir = sys_get_temp_dir().'/idempotency-fenced-'.Str::uuid()->toString();
    File::makeDirectory($dir, 0777, true);
    $userId = User::factory()->create()->id;
    $started = $dir.'/started';
    $resume = $dir.'/resume';
    $outOriginal = $dir.'/original.json';
    $outReclaimer = $dir.'/reclaimer.json';
    $pids = [];

    try {
        $pids[] = forkIdempotencyRequest([
            'key' => 'fenced-live',
            'user_id' => $userId,
            'owner' => 'original',
            'out' => $outOriginal,
            'started' => $started,
            'resume' => $resume,
        ]);

        awaitIdempotencyFile($started);
        $claim = IdempotencyKey::on('idempotency_second')
            ->where(IdempotencyKeysSchema::KEY, 'fenced-live')
            ->firstOrFail();
        $originalToken = (string) $claim->{IdempotencyKeysSchema::CLAIM_TOKEN};

        IdempotencyKey::on('idempotency_second')
            ->whereKey($claim->getKey())
            ->update([IdempotencyKeysSchema::EXPIRES_AT => now()->subSecond()]);

        $pids[] = forkIdempotencyRequest([
            'key' => 'fenced-live',
            'user_id' => $userId,
            'owner' => 'reclaimer',
            'out' => $outReclaimer,
        ]);

        waitIdempotencyChildren($pids[1]);
        file_put_contents($resume, 'go');
        waitIdempotencyChildren($pids[0]);
        $pids = [];

        $original = readIdempotencyOutcome($outOriginal);
        $reclaimer = readIdempotencyOutcome($outReclaimer);
        $row = IdempotencyKey::on('idempotency_second')
            ->where(IdempotencyKeysSchema::KEY, 'fenced-live')
            ->firstOrFail();

        expect($original['code'] ?? null)->toBe('core.idempotency.unresolved')
            ->and($reclaimer['status'] ?? null)->toBe(200)
            ->and($row->{IdempotencyKeysSchema::CLAIM_TOKEN})->not->toBe($originalToken)
            ->and($row->{IdempotencyKeysSchema::RESPONSE})->toBe(['data' => ['owner' => 'reclaimer', 'created' => true]]);
    } finally {
        if ($pids !== []) {
            if (! file_exists($resume)) {
                file_put_contents($resume, 'go');
            }

            waitIdempotencyChildren(...$pids);
        }

        File::deleteDirectory($dir);
    }
});
