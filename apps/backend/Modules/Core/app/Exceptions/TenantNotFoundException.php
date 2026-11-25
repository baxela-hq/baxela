<?php

namespace Modules\Core\Exceptions;

use Modules\Core\Contracts\Gateways\Tenant\DTOs\TenantSnapshotDto;

/**
 * Identification could not establish a serving tenant (multi-tenancy
 * §3.1): an unknown host outside the fallback list, an unknown X-Tenant-Id
 * or slug, a suspended store, or an admin request that named no store.
 * Client-facing and safe — the request points at a store that cannot be
 * served; the static constructors give each outcome its own code.
 */
class TenantNotFoundException extends BaseException
{
    /** @param  array<string, mixed>  $meta */
    private function __construct(string $code, int $status, string $reason, array $meta = [])
    {
        parent::__construct(code: $code, httpStatus: $status, meta: ['reason' => $reason, ...$meta]);
    }

    /**
     * Nothing resolved the request to a store.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function unresolved(string $reason, array $meta = []): self
    {
        return new self('core.tenant.not_found', 404, $reason, $meta);
    }

    /** The requested store exists but is suspended (§3.1). */
    public static function suspended(TenantSnapshotDto $tenant): self
    {
        return new self('core.tenant.suspended', 403, 'suspended', ['tenant' => $tenant->slug]);
    }

    /**
     * An admin request on a central host named no store (§3.1, A5).
     *
     * @param  list<string>  $memberships
     */
    public static function selectionRequired(array $memberships): self
    {
        return new self('core.tenant.selection_required', 422, 'selection_required', ['memberships' => $memberships]);
    }

    /** The caller holds no active membership in any store (§3.1). */
    public static function noMembership(): self
    {
        return new self('core.tenant.membership_required', 403, 'no_membership');
    }
}
