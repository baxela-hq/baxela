<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Scope;

/*
|--------------------------------------------------------------------------
| Octane acceptance-gate endpoints (never loaded in production)
|--------------------------------------------------------------------------
|
| Mounted from bootstrap/app.php only when OCTANE_GATE=1 is set and the
| app is not in production. They exist so
| scripts/octane-isolation-gate.php can prove persistent-worker isolation
| against a real RoadRunner server: worker identity, authentication,
| permissions, log context and Sentry scope tags must never leak between
| consecutive requests handled by the same worker.
|
| These routes ride the same `api` middleware group as every module route
| (AssignRequestId, throttling, security headers), so the isolation
| guarantees are tested on the real request path.
|
*/

Route::middleware('api')->prefix('_octane-gate')->group(function (): void {
    Route::get('/worker', fn () => [
        'pid' => getmypid(),
    ]);

    // Echoes the client IP exactly as Laravel resolved it after the custom
    // TrustProxies middleware ran. A trusted peer's forwarded headers are
    // honored; an untrusted peer's are ignored (real peer IP returned).
    Route::get('/ip', fn (Request $request) => [
        'ip' => $request->ip(),
        'xff' => $request->header('x-forwarded-for'),
    ]);

    // Extension lifecycle observable: the row state re-reads every request
    // (ExtensionManager::flush listener), while the boot-level effect —
    // config('payment.drivers.sandbox') — only exists on workers booted
    // after the extension passed its boot guards. The gap between the two
    // is exactly what distinguishes per-request flush from per-worker boot.
    Route::get('/ext', function () {
        $manager = app(\Modules\Core\Extensions\ExtensionManager::class);
        $row = $manager->row('baxela/baxela-sandbox-gateway');

        return [
            'row' => $row !== null,
            'enabled' => (bool) $row?->{\Modules\Core\Schemas\Extension\ExtensionSchema::ENABLED},
            'skip' => $manager->skipReason('baxela/baxela-sandbox-gateway'),
            'sandbox_driver' => config('payment.drivers.sandbox'),
        ];
    });

    // 401 when unauthenticated — a stale authenticated context on the
    // worker would surface here as a wrong id or a missing 401.
    Route::get('/me', fn (Request $request) => [
        'id' => $request->user()->id,
    ])->middleware('auth:sanctum');

    Route::get('/can', fn (Request $request) => [
        'id' => $request->user()->id,
        'can' => $request->user()->can('octane-gate.permission'),
    ])->middleware('auth:sanctum');

    // A log record tagged with this marker carries the request's shared
    // log context (request-id), which the gate inspects for leakage.
    Route::get('/log/{marker}', fn (string $marker) => [
        'logged' => Log::warning("GATE {$marker}") !== null,
    ]);

    // Sentry 4.x scopes are write-only (no getTags()); the only public way
    // to read a scope back is applying it to a synthetic event. The scope
    // object id additionally detects a scope that was NOT replaced between
    // requests on the same worker. The user id detects identity leakage
    // sentry-laravel attaches for authenticated requests.
    $scopeTags = static function (): array {
        $tags = [];
        $scopeId = null;
        $user = null;
        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope) use (&$tags, &$scopeId, &$user): void {
            $scopeId = spl_object_id($scope);
            $event = $scope->applyToEvent(Event::createEvent());
            $tags = $event?->getTags() ?? [];
            $user = $event?->getUser()?->getId();
        });

        return ['tags' => $tags, 'scope_id' => $scopeId, 'user' => $user];
    };

    Route::get('/scope', fn () => $scopeTags());

    // Sets a UNIQUE marker tag in the current scope, then reads the scope
    // back. Because each request marks with its own value, a marker
    // surviving into the next request proves the scope was not flushed
    // between requests.
    Route::get('/scope-mark/{value}', function (string $value) use ($scopeTags): array {
        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope) use ($value): void {
            $scope->setTag('gate-marker', $value);
        });

        return $scopeTags();
    });

    Route::post('/throw', fn (): never => throw new RuntimeException('octane-gate intentional failure'));
});
