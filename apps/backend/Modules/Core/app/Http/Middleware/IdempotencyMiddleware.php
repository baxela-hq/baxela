<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Models\IdempotencyKey;
use Modules\Core\Schemas\IdempotencyKeys\IdempotencyKeysSchema;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public const string IDEMPOTENCY_REQUEST_KEY = 'X-Idempotency-Key';

    public const string IDEMPOTENCY_RESPONSE_KEY = 'X-Idempotency-Response';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $idempotencyHeaderKey = $request->header(self::IDEMPOTENCY_REQUEST_KEY);

        if (empty($idempotencyHeaderKey)) {
            return $next($request);
        }

        $idempotencyRecord = IdempotencyKey::query()->where([
            [IdempotencyKeysSchema::USER_ID, '=', $request->user()->id],
            [IdempotencyKeysSchema::KEY, '=', $idempotencyHeaderKey],
            [IdempotencyKeysSchema::EXPIRED_AT, '>', now()->toDateTimeString()],
        ])->first();
        if ($idempotencyRecord) {
            return response()->json($idempotencyRecord->{IdempotencyKeysSchema::RESPONSE});
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($response->headers->has(static::IDEMPOTENCY_RESPONSE_KEY)) {
            return;
        }

        if ($response->isSuccessful() && $request->hasHeader(self::IDEMPOTENCY_REQUEST_KEY)) {
            $response->headers->add([
                static::IDEMPOTENCY_RESPONSE_KEY => 'true',
            ]);
            IdempotencyKey::query()->create([
                IdempotencyKeysSchema::USER_ID => $request->user()->id,
                IdempotencyKeysSchema::KEY => $request->header(self::IDEMPOTENCY_REQUEST_KEY),
                IdempotencyKeysSchema::RESPONSE => json_decode($response->getContent(), true),
                IdempotencyKeysSchema::EXPIRED_AT => now()->addMinutes(1),
            ]);
        }
    }
}
