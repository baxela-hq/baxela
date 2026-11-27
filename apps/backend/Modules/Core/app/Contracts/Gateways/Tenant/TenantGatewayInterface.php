<?php

namespace Modules\Core\Contracts\Gateways\Tenant;

use Modules\Core\Contracts\Gateways\Tenant\DTOs\TenantSnapshotDto;

/**
 * Core-facing tenant lookup contract (multi-tenancy §2). Implemented by the
 * Tenant module's gateway, bound in its provider — Core never reaches into
 * the Tenant module's models or schemas.
 *
 * Every method here is a deliberate global lookup (tenant resolution,
 * membership checks, tenant selection); none of them is a store-scoped
 * read. Store-owned rows go through TenantScopedTrait instead.
 */
interface TenantGatewayInterface
{
    /** Resolve an active-or-suspended tenant by one of its verified domains. */
    public function resolveByDomain(string $host): ?TenantSnapshotDto;

    public function find(int $id): ?TenantSnapshotDto;

    public function findBySlug(string $slug): ?TenantSnapshotDto;

    /**
     * Every known tenant id, for installation-wide integrity checks.
     *
     * @return list<int>
     */
    public function allIds(): array;

    public function defaultTenant(): ?TenantSnapshotDto;

    public function isMembershipActive(int $userId, int $tenantId): bool;

    /**
     * The user's active memberships for tenant selection (§3.1) — id and
     * slug only; authorization is never derived from this list.
     *
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function membershipsFor(int $userId): array;
}
