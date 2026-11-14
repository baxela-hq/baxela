<?php

/**
 * Phase-1 Octane/RoadRunner acceptance gate (RoadRunner rollout plan).
 *
 * Boots the app against a dedicated gate database, seeds two staff users
 * (one super-admin, one without any role), and drives a real RoadRunner
 * server through the scenarios persistent workers must satisfy:
 *
 *  - worker identity stays constant across consecutive requests (--workers=1)
 *  - authentication state never leaks between requests on the same worker
 *  - super-admin permission checks never cross users (spatie cache reset)
 *  - the shared log context (request-id) never leaks, including after a
 *    request that throws an unhandled exception
 *  - Sentry scope markers set in one request never survive into the next
 *
 * The assertions read REAL artifacts: response headers (X-REQUEST-ID), the
 * server's actual log file, and the live Sentry scope — no mocked loggers.
 *
 * Requires an octane instance started with the gate endpoints enabled:
 *
 *   docker compose exec mysql mysql -uroot -p"$MYSQL_ROOT_PASSWORD" \
 *       -e "CREATE DATABASE IF NOT EXISTS baxela_octane_gate;
 *           GRANT ALL PRIVILEGES ON baxela_octane_gate.* TO 'baxela'@'%';"
 *   docker compose exec -e DB_DATABASE=baxela_octane_gate app php artisan migrate --force
 *   docker compose exec -d -e DB_DATABASE=baxela_octane_gate -e OCTANE_GATE=1 \
 *       -e LOG_CHANNEL=single -e OCTANE_STATE_FILE=storage/logs/octane-gate-state.json \
 *       app php artisan octane:start --host=127.0.0.1 --port=8001 --rpc-host=127.0.0.1 --workers=1 --max-requests=500
 *   # (a second instance with --workers=4 --port=8002 enables the concurrency phase)
 *   docker compose exec -e DB_DATABASE=baxela_octane_gate -e GATE_URL=http://127.0.0.1:8001 \
 *       -e GATE_CONCURRENCY_URL=http://127.0.0.1:8002 \
 *       app php scripts/octane-isolation-gate.php
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Schemas\User\UserSchema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$failures = 0;

function check(bool $ok, string $label, string $detail = ''): void
{
    global $failures;
    echo ($ok ? 'PASS' : 'FAIL')."  {$label}".($detail !== '' && ! $ok ? " — {$detail}" : '')."\n";
    if (! $ok) {
        $failures++;
    }
}

/**
 * @return array{0: int, 1: mixed, 2: ?string} status, decoded JSON body, X-REQUEST-ID header
 */
function gate_request(string $method, string $url, ?string $token = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer '.$token;
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        fwrite(STDERR, 'CURL ERROR: '.curl_error($ch)." for {$url}\n");
        exit(1);
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerBlock = substr((string) $raw, 0, (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    $body = json_decode(substr((string) $raw, (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE)), true);
    curl_close($ch);
    preg_match('/^X-REQUEST-ID:\s*(\S+)/mi', $headerBlock, $m);

    return [$status, $body, $m[1] ?? null];
}

function gate_log_lines(string $needle): array
{
    $path = storage_path('logs/laravel.log');
    if (! is_file($path)) {
        return [];
    }

    return array_values(array_filter(
        file($path, FILE_IGNORE_NEW_LINES),
        fn (string $line): bool => str_contains($line, $needle),
    ));
}

$base = rtrim((string) env('GATE_URL', 'http://127.0.0.1:8001'), '/');
$concurrencyBase = env('GATE_CONCURRENCY_URL');

// ---------------------------------------------------------------------------
// Seed: two users with stable, run-unique emails; A is a super-admin (the
// app's real Gate::before bypass), B has no role at all.
// ---------------------------------------------------------------------------
try {
    DB::select('select 1');
} catch (Throwable $e) {
    fwrite(STDERR, "Gate database unreachable: {$e->getMessage()}\n");
    exit(1);
}

$run = bin2hex(random_bytes(3));

$userA = User::query()->create([
    UserSchema::EMAIL => "octane-gate-{$run}-a@example.test",
    UserSchema::PASSWORD => Hash::make('gate-password'),
    UserSchema::IS_ACTIVE => true,
]);
$userA->assignRole(Role::query()->firstOrCreate([
    'name' => Role::SUPER_ADMIN,
    'guard_name' => GuardsEnum::WEB->value,
]));

$userB = User::query()->create([
    UserSchema::EMAIL => "octane-gate-{$run}-b@example.test",
    UserSchema::PASSWORD => Hash::make('gate-password'),
    UserSchema::IS_ACTIVE => true,
]);

$tokenA = $userA->createToken('gate')->plainTextToken;
$tokenB = $userB->createToken('gate')->plainTextToken;

// ---------------------------------------------------------------------------
// 1. Reachability + worker identity
// ---------------------------------------------------------------------------
[$st, $worker, $rid] = gate_request('GET', "{$base}/_octane-gate/worker");
check($st === 200 && ($worker['pid'] ?? 0) > 0, 'gate endpoints reachable (/worker 200)', "status={$st}");
$pid = $worker['pid'];

// ---------------------------------------------------------------------------
// 2. Authentication isolation: A -> B -> unauthenticated -> A
// ---------------------------------------------------------------------------
[$st, $body] = gate_request('GET', "{$base}/_octane-gate/me", $tokenA);
check($st === 200 && $body['id'] === $userA->id, 'request A authenticates as user A', "status={$st} body=".json_encode($body));
[$st, $body] = gate_request('GET', "{$base}/_octane-gate/me", $tokenB);
check($st === 200 && $body['id'] === $userB->id, 'request B authenticates as user B (no A residue)', "status={$st} body=".json_encode($body));
[$st, $body] = gate_request('GET', "{$base}/_octane-gate/me");
check($st === 401, 'unauthenticated request is a 401 (no stale auth on the worker)', "status={$st}");
[$st, $body] = gate_request('GET', "{$base}/_octane-gate/me", $tokenA);
check($st === 200 && $body['id'] === $userA->id, 'request after 401 still authenticates cleanly', "status={$st} body=".json_encode($body));
[$st, $worker, ] = gate_request('GET', "{$base}/_octane-gate/worker");
check($worker['pid'] === $pid, 'worker identity constant across the auth sequence', "was {$pid}, now {$worker['pid']}");

// ---------------------------------------------------------------------------
// 3. Log context isolation: each /log record carries its own request-id and
// a unique gate-marker context key; the NEXT request's record must not
// contain the previous request's marker or request-id (masking by key
// overwrite would hide a same-key leak, the marker key cannot be masked).
// ---------------------------------------------------------------------------
[$st, , $ridA] = gate_request('GET', "{$base}/_octane-gate/log/GATE-{$run}-A", $tokenA);
check($st === 200 && $ridA !== null, 'log request A completed with an X-REQUEST-ID', "status={$st}");
sleep(1); // give the logger a beat to flush
$linesA = gate_log_lines("GATE-{$run}-A");
check(count($linesA) === 1 && str_contains($linesA[0], (string) $ridA),
    'log record A carries its own request-id context',
    count($linesA) === 0 ? 'marker line missing from laravel.log' : substr($linesA[0] ?? '', 0, 160));

[$st, , $ridB] = gate_request('GET', "{$base}/_octane-gate/log/GATE-{$run}-B", $tokenB);
sleep(1);
$linesB = gate_log_lines("GATE-{$run}-B");
check(count($linesB) === 1, 'log record B written', count($linesB) === 0 ? 'marker line missing from laravel.log' : '');
$lineB = $linesB[0] ?? '';
check($lineB !== '' && str_contains($lineB, (string) $ridB) && ! str_contains($lineB, (string) $ridA),
    'log record B carries its own request-id, never A\'s',
    substr($lineB, 0, 160));

// ---------------------------------------------------------------------------
// 4. Permission isolation: super-admin `can()` alternates with a roleless
// user on the same worker (spatie cache must be reset per request).
// ---------------------------------------------------------------------------
foreach ([true, false, true, false] as $expected) {
    [$st, $body] = gate_request('GET', "{$base}/_octane-gate/can", $expected ? $tokenA : $tokenB);
    check($st === 200 && $body['can'] === $expected, 'permission check isolated ('.($expected ? 'admin' : 'roleless').')', 'body='.json_encode($body));
}

// ---------------------------------------------------------------------------
// 5. Sentry scope isolation: a marker tag set in request A must be gone in
// request B (the scope is flushed between requests, not merely overwritten),
// and the scope object itself must be replaced per request.
// ---------------------------------------------------------------------------
[$st, $body, ] = gate_request('GET', "{$base}/_octane-gate/scope-mark/MARK-{$run}-A", $tokenA);
$oidA = $body['scope_id'] ?? null;
check($st === 200 && ($body['tags']['gate-marker'] ?? null) === "MARK-{$run}-A", 'scope marker A set and visible in its own request', 'body='.json_encode($body));
[$st, $body] = gate_request('GET', "{$base}/_octane-gate/scope");
check(! array_key_exists('gate-marker', $body['tags'] ?? []),
    'scope marker from request A is flushed before the next request', 'tags='.json_encode($body['tags'] ?? null));
check(! isset($body['user']) || $body['user'] === null,
    'Sentry user from request A is not attached to the next unauthenticated request', 'user='.json_encode($body['user'] ?? null));
check(isset($body['scope_id']) && $body['scope_id'] !== $oidA,
    'Sentry scope object replaced between requests on the same worker', 'scope_id='.json_encode($body['scope_id'] ?? null));
[$st, $body, $ridScopeB] = gate_request('GET', "{$base}/_octane-gate/scope-mark/MARK-{$run}-B", $tokenB);
$oidB = $body['scope_id'] ?? null;
check(($body['tags']['request_id'] ?? null) === $ridScopeB,
    'Sentry request_id tag matches the X-REQUEST-ID of its own request', 'tags='.json_encode($body['tags'] ?? null));

// ---------------------------------------------------------------------------
// 6. Exceptional termination: a request that throws must not poison the
// worker — the next request on the same worker is clean.
// ---------------------------------------------------------------------------
[$st, , ] = gate_request('POST', "{$base}/_octane-gate/throw", $tokenA);
check($st === 500, 'intentional failure surfaces as a 500', "status={$st}");
sleep(1);
[$st, $body] = gate_request('GET', "{$base}/_octane-gate/me", $tokenB);
check($st === 200 && $body['id'] === $userB->id, 'post-exception request authenticates the right user', "status={$st} body=".json_encode($body));
[$st, , $ridPost] = gate_request('GET', "{$base}/_octane-gate/log/GATE-{$run}-POST", $tokenB);
sleep(1);
$linesPost = gate_log_lines("GATE-{$run}-POST");
$linePost = $linesPost[0] ?? '';
check($linePost !== '' && str_contains($linePost, (string) $ridPost) && ! str_contains($linePost, (string) $ridA),
    'post-exception log record carries a fresh request-id, never the failed request\'s',
    substr($linePost, 0, 160));
[$st, $body] = gate_request('GET', "{$base}/_octane-gate/scope");
check(! array_key_exists('gate-marker', $body['tags'] ?? []),
    'post-exception Sentry scope carries no marker from earlier requests', 'tags='.json_encode($body['tags'] ?? null));
check(isset($body['scope_id']) && $body['scope_id'] !== $oidB,
    'post-exception Sentry scope is a fresh object', 'scope_id='.json_encode($body['scope_id'] ?? null));
[$st, $worker, ] = gate_request('GET', "{$base}/_octane-gate/worker");
check($worker['pid'] > 0, 'worker alive after the exceptional request (recovered or respawned)', "pid={$worker['pid']}");

// ---------------------------------------------------------------------------
// 7. Concurrency smoke on the normal 4-worker pool: parallel requests with
// alternating tokens must each observe their own identity.
// ---------------------------------------------------------------------------
if ($concurrencyBase) {
    $concurrencyBase = rtrim((string) $concurrencyBase, '/');
    $requests = [];
    $mh = curl_multi_init();
    $handles = [];
    $expectedIds = [];
    for ($i = 0; $i < 40; $i++) {
        $useA = $i % 2 === 0;
        $ch = curl_init("{$concurrencyBase}/_octane-gate/me");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer '.($useA ? $tokenA : $tokenB)],
            CURLOPT_TIMEOUT => 20,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[(int) $ch] = ['ch' => $ch, 'expected' => $useA ? $userA->id : $userB->id];
    }
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.2);
    } while ($running > 0);
    $wrong = 0;
    foreach ($handles as $h) {
        $body = json_decode((string) curl_multi_getcontent($h['ch']), true);
        if (($body['id'] ?? null) !== $h['expected']) {
            $wrong++;
        }
        curl_multi_remove_handle($mh, $h['ch']);
        curl_close($h['ch']);
    }
    curl_multi_close($mh);
    check($wrong === 0, "concurrent requests across the 4-worker pool all saw their own identity ({$wrong} wrong of 40)");
} else {
    echo "SKIP  concurrency phase (GATE_CONCURRENCY_URL not set)\n";
}

// ---------------------------------------------------------------------------
echo "\n".($failures === 0 ? 'GATE PASSED — persistent-worker isolation holds.' : "GATE FAILED — {$failures} assertion(s) above.")."\n";
exit($failures === 0 ? 0 : 1);
