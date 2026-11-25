<?php

namespace Modules\Core\Http\Middleware;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Contracts\Gateways\Tenant\DTOs\TenantSnapshotDto;
use Modules\Core\Contracts\Gateways\Tenant\TenantGatewayInterface;

/**
 * Machine-audience tenant resolution (multi-tenancy §3.1/§9.7): the
 * tenant comes from the bearer token's stored `tenant_id` — never from a
 * header. A machine token is issued for exactly one store; its tenant_id
 * is immutable after issuance; ordinary user tokens carry none.
 *
 * Fail closed on every token problem — missing, unknown, expired, revoked
 * (row deleted), wildcard-ability, or no tenant bound — by returning null,
 * which ResolveTenant turns into a 404.
 */
class ResolvesMachineTenant
{
    public function __construct(private readonly TenantGatewayInterface $gateway) {}

    public function resolve(Request $request): ?TenantSnapshotDto
    {
        $bearer = $request->bearerToken();

        if ($bearer === null || $bearer === '') {
            return null;
        }

        $token = PersonalAccessToken::findToken($bearer);

        if ($token === null) {
            // Unknown or revoked (revocation is row deletion).
            return null;
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return null;
        }

        // Wildcard tokens are refused at mint time; refuse them here too
        // (defense in depth — gap-C2 D14).
        if (in_array('*', (array) $token->abilities, true)) {
            return null;
        }

        /** @var int|null $tenantId */
        $tenantId = $token->getAttribute('tenant_id');

        // An ordinary user token is not a machine token: it has no store.
        if ($tenantId === null) {
            return null;
        }

        return $this->gateway->find((int) $tenantId);
    }
}
