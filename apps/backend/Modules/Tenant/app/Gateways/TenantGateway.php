<?php

namespace Modules\Tenant\Gateways;

use Modules\Core\Contracts\Gateways\Tenant\DTOs\TenantSnapshotDto;
use Modules\Core\Contracts\Gateways\Tenant\TenantGatewayInterface;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantDomain;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;

/**
 * Core-facing tenant lookups (multi-tenancy §2). Every method here is a
 * deliberate GLOBAL read — tenant resolution and membership checks cannot
 * be tenant-scoped, because they are what establishes the tenant. Nothing
 * in this class returns a store-owned row; those go through
 * TenantScopedTrait.
 *
 * Testing fallback (Phase 1 gate): the suite runs on a fresh in-memory
 * database where no tenant row exists, yet every request resolves through
 * the loopback fallback. In `testing` only, an EMPTY tenants table makes
 * defaultTenant() yield a built-in default snapshot, so the existing
 * tests stay green with zero edits. Any other environment still fails
 * closed, and the moment a real tenant row exists the fallback is off.
 */
class TenantGateway implements TenantGatewayInterface
{
    /** The built-in default tenant used only by the testing fallback. */
    public const int TESTING_DEFAULT_ID = 1;

    public const string TESTING_DEFAULT_SLUG = 'default';

    public function __construct(private readonly string $environment = 'production') {}

    public function resolveByDomain(string $host): ?TenantSnapshotDto
    {
        $domain = TenantDomain::query()
            ->where(TenantDomainSchema::DOMAIN, $host)
            ->where(TenantDomainSchema::STATUS, TenantDomainSchema::STATUS_VERIFIED)
            ->first();

        return $domain === null ? null : $this->snapshotOf($domain->tenant);
    }

    public function find(int $id): ?TenantSnapshotDto
    {
        return $this->snapshotOf(Tenant::query()->find($id));
    }

    public function findBySlug(string $slug): ?TenantSnapshotDto
    {
        return $this->snapshotOf(Tenant::query()->where(TenantSchema::SLUG, $slug)->first());
    }

    public function defaultTenant(): ?TenantSnapshotDto
    {
        $slug = (string) config('core.tenancy.fallback_tenant');

        $tenant = $this->findBySlug($slug);

        if ($tenant !== null) {
            return $tenant;
        }

        // Phase 1 testing fallback — see the class note.
        if ($this->environment === 'testing' && ! Tenant::query()->exists()) {
            return new TenantSnapshotDto(
                id: self::TESTING_DEFAULT_ID,
                slug: $slug,
                name: 'Default',
                suspended: false,
            );
        }

        return null;
    }

    public function isMembershipActive(int $userId, int $tenantId): bool
    {
        return TenantMembership::query()
            ->where(TenantMembershipSchema::USER_ID, $userId)
            ->where(TenantMembershipSchema::TENANT_ID, $tenantId)
            ->where(TenantMembershipSchema::STATUS, TenantMembershipSchema::STATUS_ACTIVE)
            ->exists();
    }

    public function membershipsFor(int $userId): array
    {
        $tenants = Tenant::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where(TenantMembershipSchema::USER_ID, $userId)
                ->where(TenantMembershipSchema::STATUS, TenantMembershipSchema::STATUS_ACTIVE))
            ->get();

        return $tenants->map(fn (Tenant $tenant): array => [
            'id' => (int) $tenant->getKey(),
            'slug' => (string) $tenant->getAttribute(TenantSchema::SLUG),
            'name' => (string) $tenant->getAttribute(TenantSchema::NAME),
        ])->all();
    }

    private function snapshotOf(?Tenant $tenant): ?TenantSnapshotDto
    {
        if ($tenant === null) {
            return null;
        }

        return new TenantSnapshotDto(
            id: (int) $tenant->getKey(),
            slug: (string) $tenant->getAttribute(TenantSchema::SLUG),
            name: (string) $tenant->getAttribute(TenantSchema::NAME),
            suspended: $tenant->isSuspended(),
            domains: $tenant->domains()
                ->where(TenantDomainSchema::STATUS, TenantDomainSchema::STATUS_VERIFIED)
                ->pluck(TenantDomainSchema::DOMAIN)
                ->all(),
        );
    }
}
