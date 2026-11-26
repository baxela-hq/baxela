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
 * The singleton lives for the process lifetime — under php-fpm one
 * request per process, under `queue:work` many jobs in one process, and
 * under Pest one process for the whole suite. Identification resets it at
 * the start of every request, every job attempt restores the snapshot its
 * JobProcessing pushed (§6.1), and `TestCase::setUp` resets it per test.
 *
 * Note for maintainers: a plain assignment is used on purpose where a
 * value is cached — `??=` on a typed property is not type-checked by the
 * analyser.
 *
 * @phpstan-type ContextState array{mode: int, tenantId: int|null, snapshot: TenantSnapshotDto|null, centralOperation: string|null}
 */
final class TenantContext
{
    public const int MODE_INACTIVE = 0;

    public const int MODE_TENANT = 1;

    public const int MODE_CENTRAL = 2;

    private int $mode = self::MODE_INACTIVE;

    private ?int $tenantId = null;

    private ?TenantSnapshotDto $snapshot = null;

    /** The registry operation behind the current central run, if any. */
    private ?string $centralOperation = null;

    /** @var list<ContextState> */
    private array $snapshots = [];

    public function __construct(private readonly TenantGatewayInterface $gateway) {}

    /** @internal resolver middleware, the queue listeners and CentralOperations only */
    public function forTenant(int $id): void
    {
        $this->restore([
            'mode' => self::MODE_TENANT,
            'tenantId' => $id,
            'snapshot' => null,
            'centralOperation' => null,
        ]);
    }

    /** @internal CentralOperations only (architecture-enforced) */
    public function central(string $operationId): void
    {
        $this->restore([
            'mode' => self::MODE_CENTRAL,
            'tenantId' => null,
            'snapshot' => null,
            'centralOperation' => $operationId,
        ]);
    }

    /** @internal */
    public function deactivate(): void
    {
        $this->restore([
            'mode' => self::MODE_INACTIVE,
            'tenantId' => null,
            'snapshot' => null,
            'centralOperation' => null,
        ]);
    }

    /** Full reset: no mode, no tenant, no snapshot stack, no central operation. */
    public function reset(): void
    {
        $this->deactivate();
        $this->snapshots = [];
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

    /** The resolved tenant's identity — null unless in tenant mode. */
    public function tenant(): ?TenantSnapshotDto
    {
        if ($this->mode !== self::MODE_TENANT) {
            return null;
        }

        if ($this->snapshot === null) {
            $this->snapshot = $this->gateway->find($this->tenantId);
        }

        return $this->snapshot;
    }

    /** The operation behind the current central run — null outside central mode. */
    public function centralOperation(): ?string
    {
        return $this->mode === self::MODE_CENTRAL ? $this->centralOperation : null;
    }

    /**
     * Run $fn as the given tenant and restore the exact previous state in
     * `finally` — on a return, on an exception, and under nested calls.
     *
     * Watch the queue stamp: `dispatch()` returns a PendingDispatch that is
     * sent when it is destroyed, so returning `dispatch(...)` from $fn sends
     * the job after the tenant was restored. Dispatch as a statement.
     */
    public function runForTenant(int $id, callable $fn): mixed
    {
        return $this->runWith(
            [
                'mode' => self::MODE_TENANT,
                'tenantId' => $id,
                'snapshot' => null,
                'centralOperation' => null,
            ],
            $fn,
        );
    }

    /**
     * @internal CentralOperations only
     *
     * @param  callable  $fn  Dispatch as a statement, see runForTenant().
     */
    public function runCentral(string $operationId, callable $fn): mixed
    {
        return $this->runWith(
            [
                'mode' => self::MODE_CENTRAL,
                'tenantId' => null,
                'snapshot' => null,
                'centralOperation' => $operationId,
            ],
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

    /**
     * Queue lifecycle (§6.1). A job attempt pushes the state it found,
     * initializes from its payload, and pops that snapshot when it ends —
     * a stack, so nested (synchronous) jobs unwind in order and the
     * enclosing request's own tenant is what remains.
     *
     * @internal queue lifecycle listeners only
     */
    public function pushSnapshot(): void
    {
        $this->snapshots[] = $this->capture();
    }

    /** @internal queue lifecycle listeners only */
    public function popSnapshot(): void
    {
        $previous = array_pop($this->snapshots);

        if ($previous !== null) {
            $this->restore($previous);
        }
    }

    /** @return ContextState */
    private function capture(): array
    {
        return [
            'mode' => $this->mode,
            'tenantId' => $this->tenantId,
            'snapshot' => $this->snapshot,
            'centralOperation' => $this->centralOperation,
        ];
    }

    /** @param  ContextState  $state */
    private function restore(array $state): void
    {
        $this->mode = $state['mode'];
        $this->tenantId = $state['tenantId'];
        $this->snapshot = $state['snapshot'];
        $this->centralOperation = $state['centralOperation'];
    }

    /** @param  ContextState  $next */
    private function runWith(array $next, callable $fn): mixed
    {
        $previous = $this->capture();

        $this->restore($next);

        try {
            return $fn();
        } finally {
            $this->restore($previous);
        }
    }
}
