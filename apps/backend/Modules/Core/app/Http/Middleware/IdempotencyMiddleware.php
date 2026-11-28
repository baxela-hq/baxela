<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Events\Tenant\IdempotencyUnresolvedEvent;
use Modules\Core\Exceptions\IdempotencyInProgressException;
use Modules\Core\Exceptions\IdempotencyUnresolvedException;
use Modules\Core\Models\IdempotencyKey;
use Modules\Core\Schemas\IdempotencyKeys\IdempotencyKeysSchema;
use Modules\Core\Support\Tenancy\IdempotencyIdentity;
use Modules\Core\Support\Tenancy\TenantWriteStamp;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Idempotency is concurrency control, not just a response cache
 * (multi-tenancy §6.6). A claim is reserved before the operation runs,
 * completed with a fenced UPDATE in handle(), and never deleted once its
 * business transaction committed.
 */
class IdempotencyMiddleware
{
    public const string IDEMPOTENCY_REQUEST_KEY = 'X-Idempotency-Key';

    public const string IDEMPOTENCY_RESPONSE_KEY = 'X-Idempotency-Response';

    /** @internal set by the middleware, read by the commit-marker helper */
    public const string CLAIM_ATTRIBUTE = 'idempotency.claim';

    /** @internal set by the helper when the business transaction committed */
    public const string COMMITTED_ATTRIBUTE = 'idempotency.committed';

    /**
     * Reserve the key, then run the operation.
     */
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header(self::IDEMPOTENCY_REQUEST_KEY);

        if (! is_string($key) || $key === '') {
            return $next($request);
        }

        $tenantId = TenantWriteStamp::tenantIdForWrite();
        $principalKey = IdempotencyIdentity::principal($request);

        $claim = $this->reserve($request, $tenantId, $principalKey, $key);

        if ($claim === null) {
            // A row already exists: replay, 409 in-progress, unresolved, or
            // a fresh claim after fenced reclamation of an expired one.
            $outcome = $this->conflict($request, $tenantId, $principalKey, $key);

            if ($outcome instanceof Response) {
                return $outcome;
            }

            $claim = $outcome;
        }

        $request->attributes->set(self::CLAIM_ATTRIBUTE, $claim);

        $response = $next($request);

        if (! $response->isSuccessful() && ! $request->attributes->get(self::COMMITTED_ATTRIBUTE, false)) {
            // terminate() removes only an uncommitted claim, under the same
            // fencing guard as every other deletion path.
            return $response;
        }

        $committed = (bool) $request->attributes->get(self::COMMITTED_ATTRIBUTE, false);
        $claim['committed'] = $committed;
        $request->attributes->set(self::CLAIM_ATTRIBUTE, $claim);

        if ($response->isSuccessful()) {
            try {
                $updated = $this->complete($claim, $response);

                if ($updated === 0) {
                    // Ownership was lost mid-flight. The marker helper throws
                    // when that happens inside the business transaction; if
                    // it had already committed, the response is discarded.
                    $this->unresolved($claim, 'completion_lost_ownership');

                    throw new IdempotencyUnresolvedException(['claim_id' => $claim['id']]);
                }

                $response->headers->set(self::IDEMPOTENCY_RESPONSE_KEY, 'true');

                return $response;
            } catch (IdempotencyUnresolvedException $e) {
                throw $e;
            } catch (Throwable $e) {
                $this->unresolved($claim, 'response_persist_failed');

                throw new IdempotencyUnresolvedException(['claim_id' => $claim['id']], $e);
            }
        }

        // Business work committed but the response failed. The claim stays
        // exactly where it is: a retry sees committed_at and is refused
        // rather than being handed a clean re-execution opportunity.
        $this->unresolved($claim, 'business_response_failed');

        return $response;
    }

    /**
     * Failure cleanup only — completion happens in handle(), before the
     * response reaches the framework. Every delete is fenced by our token
     * and the committed_at guard.
     */
    public function terminate(Request $request, Response $response): void
    {
        /** @var array{id: int, token: string, tenant_id: int, principal_key: string, key: string, committed: bool}|null $claim */
        $claim = $request->attributes->get(self::CLAIM_ATTRIBUTE);

        if ($claim === null || $claim['committed'] || $response->isSuccessful()
            || $response->headers->has(self::IDEMPOTENCY_RESPONSE_KEY)) {
            return;
        }

        $this->delete($claim);
    }

    /**
     * One-statement reservation. Returns the claim on success, null on a
     * conflict with the composite unique index.
     *
     * @return array{id: int, token: string, tenant_id: int, principal_key: string, key: string, committed: bool}|null
     */
    private function reserve(Request $request, int $tenantId, string $principalKey, string $key): ?array
    {
        $token = (string) Str::uuid();
        $now = now();

        $inserted = IdempotencyKey::query()->insertOrIgnore([
            'tenant_id' => $tenantId,
            IdempotencyKeysSchema::USER_ID => $request->user()?->getAuthIdentifier(),
            IdempotencyKeysSchema::PRINCIPAL_KEY => $principalKey,
            IdempotencyKeysSchema::KEY => $key,
            IdempotencyKeysSchema::STATUS => IdempotencyKeysSchema::STATUS_PROCESSING,
            IdempotencyKeysSchema::CLAIM_TOKEN => $token,
            IdempotencyKeysSchema::EXPIRES_AT => $now->copy()->addMinutes($this->ttlMinutes()),
            IdempotencyKeysSchema::CREATED_AT => $now,
        ]);

        if ($inserted !== 1) {
            return null;
        }

        /** @var IdempotencyKey $row */
        $row = IdempotencyKey::query()
            ->where(IdempotencyKeysSchema::CLAIM_TOKEN, $token)
            ->firstOrFail();

        return [
            'id' => (int) $row->getKey(),
            'token' => $token,
            'tenant_id' => $tenantId,
            'principal_key' => $principalKey,
            'key' => $key,
            'committed' => false,
        ];
    }

    /**
     * Resolve an existing row after a conflict. A Response means the caller
     * must stop; an array claim means an expired claim was reclaimed and
     * the caller may execute under the new token.
     *
     * @return Response|array{id: int, token: string, tenant_id: int, principal_key: string, key: string, committed: bool}
     */
    private function conflict(Request $request, int $tenantId, string $principalKey, string $key): Response|array
    {
        $row = $this->lookup($tenantId, $principalKey, $key);

        if ($row === null) {
            // The holder finished and cleaned up between our INSERT and
            // this read — the key is free again.
            $claim = $this->reserve($request, $tenantId, $principalKey, $key);

            if ($claim !== null) {
                return $claim;
            }

            $row = $this->lookup($tenantId, $principalKey, $key);
        }

        if ($row?->{IdempotencyKeysSchema::STATUS} === IdempotencyKeysSchema::STATUS_COMPLETED) {
            return response()
                ->json($row->{IdempotencyKeysSchema::RESPONSE} ?? [])
                ->withHeaders([self::IDEMPOTENCY_RESPONSE_KEY => 'true']);
        }

        if ($row === null) {
            throw new IdempotencyInProgressException;
        }

        if ($row->{IdempotencyKeysSchema::COMMITTED_AT} !== null) {
            $this->unresolved([
                'id' => (int) $row->getKey(),
                'tenant_id' => $tenantId,
            ], 'committed_without_response');

            throw new IdempotencyUnresolvedException(['claim_id' => (int) $row->getKey()]);
        }

        if (! $this->expired($row)) {
            throw new IdempotencyInProgressException;
        }

        // Fenced reclamation: the same row lock serialises this DELETE
        // against both the completion UPDATE and another reclaimer.
        $oldToken = (string) $row->{IdempotencyKeysSchema::CLAIM_TOKEN};
        $deleted = IdempotencyKey::query()
            ->whereKey((int) $row->getKey())
            ->where(IdempotencyKeysSchema::CLAIM_TOKEN, $oldToken)
            ->where(IdempotencyKeysSchema::STATUS, IdempotencyKeysSchema::STATUS_PROCESSING)
            ->where(IdempotencyKeysSchema::EXPIRES_AT, '<', now())
            ->whereNull(IdempotencyKeysSchema::COMMITTED_AT)
            ->delete();

        if ($deleted !== 1) {
            // Another reclaimer won the DELETE; its INSERT then won or
            // lost the unique race. Either way this caller cannot run.
            throw new IdempotencyInProgressException;
        }

        $claim = $this->reserve($request, $tenantId, $principalKey, $key);

        if ($claim === null) {
            throw new IdempotencyInProgressException;
        }

        return $claim;
    }

    private function lookup(int $tenantId, string $principalKey, string $key): ?IdempotencyKey
    {
        /** @var IdempotencyKey|null $row */
        $row = IdempotencyKey::query()
            ->where('tenant_id', $tenantId)
            ->where(IdempotencyKeysSchema::PRINCIPAL_KEY, $principalKey)
            ->where(IdempotencyKeysSchema::KEY, $key)
            ->first();

        return $row;
    }

    private function expired(IdempotencyKey $row): bool
    {
        $expiresAt = $row->{IdempotencyKeysSchema::EXPIRES_AT};

        return $expiresAt === null || $expiresAt->lessThan(now());
    }

    /**
     * @param  array{id: int, token: string, tenant_id: int, principal_key: string, key: string}  $claim
     */
    private function complete(array $claim, Response $response): int
    {
        $content = $response->getContent();

        return IdempotencyKey::query()
            ->whereKey($claim['id'])
            ->where('tenant_id', $claim['tenant_id'])
            ->where(IdempotencyKeysSchema::PRINCIPAL_KEY, $claim['principal_key'])
            ->where(IdempotencyKeysSchema::KEY, $claim['key'])
            ->where(IdempotencyKeysSchema::CLAIM_TOKEN, $claim['token'])
            ->where(IdempotencyKeysSchema::STATUS, IdempotencyKeysSchema::STATUS_PROCESSING)
            ->update([
                IdempotencyKeysSchema::STATUS => IdempotencyKeysSchema::STATUS_COMPLETED,
                IdempotencyKeysSchema::RESPONSE => json_decode($content === false ? '' : $content, true),
                IdempotencyKeysSchema::EXPIRES_AT => now()->addMinutes($this->ttlMinutes()),
            ]);
    }

    /** @param array{id: int, token: string, tenant_id: int} $claim */
    private function delete(array $claim): void
    {
        IdempotencyKey::query()
            ->whereKey($claim['id'])
            ->where('tenant_id', $claim['tenant_id'])
            ->where(IdempotencyKeysSchema::CLAIM_TOKEN, $claim['token'])
            ->where(IdempotencyKeysSchema::STATUS, IdempotencyKeysSchema::STATUS_PROCESSING)
            ->whereNull(IdempotencyKeysSchema::COMMITTED_AT)
            ->delete();
    }

    /** @param array{id: int, tenant_id: int} $claim */
    private function unresolved(array $claim, string $reason): void
    {
        event(new IdempotencyUnresolvedEvent(
            claim_id: $claim['id'],
            tenant_id: $claim['tenant_id'],
            reason: $reason,
        ));
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('core.idempotency.ttl_minutes', 1));
    }
}
