<?php

namespace Modules\Core\Support\Tenancy;

use Illuminate\Http\Request;
use Modules\Core\Exceptions\IdempotencyUnresolvedException;
use Modules\Core\Http\Middleware\IdempotencyMiddleware;
use Modules\Core\Models\IdempotencyKey;
use Modules\Core\Schemas\IdempotencyKeys\IdempotencyKeysSchema;

/**
 * Stamps the claim inside the business transaction (multi-tenancy §6.6).
 *
 * Exactly-one-row update: a claim lost to reclamation cannot commit
 * unmarked work. The throw rolls the whole transaction back, making the
 * marker and the business write a single commit by force.
 */
final class IdempotencyCommitMarker
{
    private function __construct() {}

    public static function mark(Request $request): void
    {
        /** @var array{id: int, token: string, tenant_id: int, principal_key: string, key: string}|null $claim */
        $claim = $request->attributes->get(IdempotencyMiddleware::CLAIM_ATTRIBUTE);

        if ($claim === null) {
            return;
        }

        $updated = IdempotencyKey::query()
            ->whereKey($claim['id'])
            ->where('tenant_id', $claim['tenant_id'])
            ->where(IdempotencyKeysSchema::PRINCIPAL_KEY, $claim['principal_key'])
            ->where(IdempotencyKeysSchema::KEY, $claim['key'])
            ->where(IdempotencyKeysSchema::CLAIM_TOKEN, $claim['token'])
            ->whereNull(IdempotencyKeysSchema::COMMITTED_AT)
            ->update([IdempotencyKeysSchema::COMMITTED_AT => now()]);

        if ($updated !== 1) {
            throw new IdempotencyUnresolvedException(['claim_id' => $claim['id']]);
        }

        $request->attributes->set(IdempotencyMiddleware::COMMITTED_ATTRIBUTE, true);
    }
}
