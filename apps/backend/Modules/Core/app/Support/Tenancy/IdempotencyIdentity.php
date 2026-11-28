<?php

namespace Modules\Core\Support\Tenancy;

use Illuminate\Http\Request;
use Modules\Core\Exceptions\IdempotencyPrincipalMissingException;
use Modules\Core\Http\Middleware\IdempotencyMiddleware;

/**
 * Stable logical identity of an idempotent request (multi-tenancy §6.6).
 *
 * The claim token changes when an expired claim is reclaimed; the identity
 * does not. External providers must key by this identity, never by the
 * claim token, so a retry after reclamation cannot duplicate an effect.
 */
final class IdempotencyIdentity
{
    public const string CART_TOKEN_HEADER = 'X-Cart-Token';

    private function __construct() {}

    /** `user:{id}` for authenticated callers, `guest:{sha256(token)}` otherwise. */
    public static function principal(Request $request): string
    {
        $user = $request->user();

        if ($user !== null) {
            return 'user:'.$user->getAuthIdentifier();
        }

        $token = $request->header(self::CART_TOKEN_HEADER);

        if (! is_string($token) || $token === '') {
            throw new IdempotencyPrincipalMissingException;
        }

        return 'guest:'.hash('sha256', $token);
    }

    /**
     * Provider-facing idempotency key for `$provider`, or null when the
     * caller sent no idempotency header (no logical request identity).
     */
    public static function providerKey(Request $request, string $provider): ?string
    {
        $header = $request->header(IdempotencyMiddleware::IDEMPOTENCY_REQUEST_KEY);

        if (! is_string($header) || $header === '') {
            return null;
        }

        $identity = implode('|', [
            TenantWriteStamp::tenantIdForWrite(),
            self::principal($request),
            $header,
            $provider,
        ]);

        return hash('sha256', $identity);
    }
}
