<?php

namespace Modules\Tenant\Gateways;

use Illuminate\Database\QueryException;
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
 * Built-in default tenant: OUTSIDE PRODUCTION ONLY, the default tenant
 * resolves to a built-in snapshot whenever no real row takes its place,
 * so a fresh checkout, a dev stack and the test suite need no seeding
 * ("single-store by zero config") — and creating another store in dev
 * never takes the loopback storefront down. Production must seed the
 * default tenant and fails closed without it. A real row always wins:
 * the built-in one is gone the moment a row with its id or slug exists.
 *
 * An uninstalled schema reads the same way: outside production, a missing
 * tenancy table answers "no rows" instead of a 500 — the test suite holds
 * route tests that never migrate a database, and tenant identification
 * reads on every request. That is the only failure forgiven here; see
 * read().
 */
class TenantGateway implements TenantGatewayInterface
{
    /** The id of the built-in default tenant. */
    public const int BUILT_IN_DEFAULT_ID = 1;

    public function __construct(private readonly string $environment = 'production') {}

    public function resolveByDomain(string $host): ?TenantSnapshotDto
    {
        $domain = $this->read(
            fn () => TenantDomain::query()
                ->where(TenantDomainSchema::DOMAIN, $host)
                ->where(TenantDomainSchema::STATUS, TenantDomainSchema::STATUS_VERIFIED)
                ->first(),
        );

        return $domain === null ? null : $this->snapshotOf($domain->tenant);
    }

    public function find(int $id): ?TenantSnapshotDto
    {
        return $this->snapshotOf($this->read(fn () => Tenant::query()->find($id))) ?? $this->builtInDefault($id);
    }

    public function findBySlug(string $slug): ?TenantSnapshotDto
    {
        return $this->snapshotOf($this->read(fn () => Tenant::query()->where(TenantSchema::SLUG, $slug)->first()));
    }

    /** @return list<int> */
    public function allIds(): array
    {
        return array_map('intval', $this->read(
            fn () => Tenant::query()->pluck(TenantSchema::ID)->all(),
        ) ?? []);
    }

    public function defaultTenant(): ?TenantSnapshotDto
    {
        $slug = (string) config('core.tenancy.fallback_tenant');

        return $this->findBySlug($slug) ?? $this->builtInDefault(self::BUILT_IN_DEFAULT_ID);
    }

    /**
     * The built-in default tenant — outside production only, and only
     * while no real row takes its place. See the class note.
     */
    private function builtInDefault(int $id): ?TenantSnapshotDto
    {
        if ($this->environment === 'production' || $id !== self::BUILT_IN_DEFAULT_ID) {
            return null;
        }

        return new TenantSnapshotDto(
            id: self::BUILT_IN_DEFAULT_ID,
            slug: (string) config('core.tenancy.fallback_tenant'),
            name: 'Default',
            suspended: false,
        );
    }

    public function isMembershipActive(int $userId, int $tenantId): bool
    {
        return $this->read(fn () => TenantMembership::query()
            ->where(TenantMembershipSchema::USER_ID, $userId)
            ->where(TenantMembershipSchema::TENANT_ID, $tenantId)
            ->where(TenantMembershipSchema::STATUS, TenantMembershipSchema::STATUS_ACTIVE)
            ->exists()) ?? false;
    }

    /**
     * @return array<int, array{id: int, slug: string, name: string}>
     */
    public function membershipsFor(int $userId): array
    {
        return $this->read(
            fn () => Tenant::query()
                ->whereHas('memberships', fn ($query) => $query
                    ->where(TenantMembershipSchema::USER_ID, $userId)
                    ->where(TenantMembershipSchema::STATUS, TenantMembershipSchema::STATUS_ACTIVE))
                ->get()
                ->map(fn (Tenant $tenant): array => [
                    'id' => (int) $tenant->getKey(),
                    'slug' => (string) $tenant->getAttribute(TenantSchema::SLUG),
                    'name' => (string) $tenant->getAttribute(TenantSchema::NAME),
                ])
                ->all(),
        ) ?? [];
    }

    /**
     * One read against the tenancy tables. Outside production, a missing
     * tenancy table answers "no rows" instead of a 500: an environment
     * whose migrations never ran serves the built-in default the same way
     * it would serve an empty schema. Nothing else is forgiven — a broken
     * or half-installed schema (any error that is not a missing table) and
     * everything in production are raised as they occurred.
     *
     * Two PostgreSQL facts shape the mechanics. A failed statement puts
     * the transaction into the aborted state, where every further
     * statement fails with 25P02 until a rollback — so the classification
     * reads the exception itself and runs no follow-up query, and the read
     * runs inside its own savepoint: rolling back to it clears the aborted
     * state while leaving any enclosing transaction (a caller's, or the
     * test harness's) untouched. On SQLite and MySQL the savepoint is a
     * harmless no-op.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $read
     * @return TReturn|null
     */
    private function read(callable $read): mixed
    {
        $connection = Tenant::resolveConnection();
        $depth = $connection->transactionLevel();
        $savepoint = $depth > 0;

        if ($savepoint) {
            $connection->beginTransaction();
        }

        try {
            return $read();
        } catch (QueryException $exception) {
            if ($this->environment === 'production' || ! $this->missingTable($exception)) {
                throw $exception;
            }

            return null;
        } finally {
            if ($savepoint && $connection->transactionLevel() > $depth) {
                $connection->rollBack();
            }
        }
    }

    /**
     * True when the failure is a missing table: PostgreSQL 42P01
     * (undefined_table), MySQL 42S02 (base_table_or_view_not_found), or
     * SQLite's "no such table" wording.
     */
    private function missingTable(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'SQLSTATE[42P01]')
            || str_contains($message, 'SQLSTATE[42S02]')
            || str_contains($message, 'no such table:');
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
