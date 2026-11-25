<?php

namespace Modules\Core\Support\Tenancy;

use Modules\Core\Contracts\Gateways\Tenant\DTOs\TenantSnapshotDto;
use Modules\Core\Contracts\Gateways\Tenant\TenantGatewayInterface;

/**
 * The request's tenant state (multi-tenancy §2) — one singleton, three
 * explicit modes, and no implicit "no tenant means see everything":
 *
 *  - INACTIVE is the default. Tenant-scoped queries THROW in it.
 *  - TENANT  is the normal request/job mode. The scope filters by tenant.
 *  - CENTRAL is the only cross-store mode, reachable solely through
 *            CentralOperations (§6.5).
 *
 * The singleton outlives the request on a persistent Octane worker, so
 * every request starts with an explicit reset (ResetTenantContext) and
 * every job attempt initializes from its payload (the queue listeners).
 */
final class TenantContext
{
    public const int MODE_INACTIVE = 0;

    public const int MODE_TENANT = 1;

    public const int MODE_CENTRAL = 2;

    private int $mode = self::MODE_INACTIVE;

    private ?int $tenantId = null;

    private ?TenantSnapshotDto $snapshot = null;

    public function __construct(private readonly TenantGatewayInterface $gateway) {}

    /** @internal resolver middleware, the queue listeners and CentralOperations only */
    public function forTenant(int $id): void
    {
        $this->mode = self::MODE_TENANT;
        $this->tenantId = $id;
        $this->snapshot = null;
    }

    /** @internal CentralOperations only (architecture-enforced) */
    public function central(): void
    {
        $this->mode = self::MODE_CENTRAL;
        $this->tenantId = null;
        $this->snapshot = null;
    }

    /** @internal */
    public function deactivate(): void
    {
        $this->mode = self::MODE_INACTIVE;
        $this->tenantId = null;
        $this->snapshot = null;
    }

    /** Full reset to the between-requests state — the Octane reset listener. */
    public function reset(): void
    {
        $this->deactivate();
    }

    public function mode(): int
    {
        return $this->mode;
    }

    /** Null unless in tenant mode. */
    public function tenantId(): ?int
    {
        return $this->mode === self::MODE_TENANT ? $this->tenantId : null;
    }

    /** The resolved tenant's identity — never null while in tenant mode. */
    public function tenant(): ?TenantSnapshotDto
    {
        if ($this->mode !== self::MODE_TENANT) {
            return null;
        }

        // A plain assignment, deliberately: `??=` on a typed property is
        // not type-checked by the analyser (verified), so it is avoided.
        if ($this->snapshot === null) {
            $this->snapshot = $this->gateway->find($this->tenantId);
        }

        return $this->snapshot;
    }

    /**
     * Run $fn as the given tenant and restore the exact previous mode in
     * `finally` — on a return, on an exception, and under nested calls.
     */
    public function runForTenant(int $id, callable $fn): mixed
    {
        return $this->runSnapshot(
            fn () => $this->applyForTenant($id),
            $fn,
        );
    }

    /**
     * Cache key prefix: "t{id}:" in tenant mode, empty otherwise. Store-
     * dependent cache keys are built through this so two stores never
     * share an entry.
     */
    public function cachePrefix(): string
    {
        return $this->mode === self::MODE_TENANT ? 't'.$this->tenantId.':' : '';
    }

    /** @internal CentralOperations only */
    public function runCentral(callable $fn): mixed
    {
        return $this->runSnapshot(
            fn () => $this->applyCentral(),
            $fn,
        );
    }

    /** @param  callable(): void  $enter */
    private function runSnapshot(callable $enter, callable $fn): mixed
    {
        $previousMode = $this->mode;
        $previousTenantId = $this->tenantId;
        $previousSnapshot = $this->snapshot;

        $enter();

        try {
            return $fn();
        } finally {
            $this->mode = $previousMode;
            $this->tenantId = $previousTenantId;
            $this->snapshot = $previousSnapshot;
        }
    }

    private function applyForTenant(int $id): void
    {
        $this->mode = self::MODE_TENANT;
        $this->tenantId = $id;
        $this->snapshot = null;
    }

    private function applyCentral(): void
    {
        $this->mode = self::MODE_CENTRAL;
        $this->tenantId = null;
        $this->snapshot = null;
    }
}
