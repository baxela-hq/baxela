<?php

namespace Modules\Tenant\Actions\Admin\TenantDomain;

use LogicException;
use Modules\Core\Support\Tenancy\CentralOperations;
use Modules\Tenant\Exceptions\TenantDomain\ReleaseFailedException;
use Modules\Tenant\Models\TenantDomain;
use Modules\Tenant\Operations\ReleaseTenantDomainOperation;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;
use Throwable;

/**
 * Releases a store's domain — a platform operation (multi-tenancy §6.5).
 * The row is kept as `released` and starts its cooling-off window, during
 * which no other store may attach the domain (§9.7). Idempotent.
 *
 * handle() is the operation's only authorized caller; execute() is its
 * registered handler.
 */
class ReleaseTenantDomainAction
{
    public function __construct(private readonly CentralOperations $central) {}

    public function handle(string $id, string $domainId): TenantDomain
    {
        $operation = new ReleaseTenantDomainOperation((int) $id, (int) $domainId);

        $result = $this->central->run($operation, static::class);

        return $result instanceof TenantDomain
            ? $result
            : throw new LogicException('The tenant-domain.release operation did not return a domain.');
    }

    /** Runs inside CentralOperations::run(). */
    public function execute(ReleaseTenantDomainOperation $operation): TenantDomain
    {
        $domain = TenantDomain::query()
            ->where(TenantDomainSchema::TENANT_ID, $operation->tenantId)
            ->whereKey($operation->domainId)
            ->firstOrFail();

        if ($domain->{TenantDomainSchema::STATUS} === TenantDomainSchema::STATUS_RELEASED) {
            return $domain;
        }

        try {
            $domain->update([
                TenantDomainSchema::STATUS => TenantDomainSchema::STATUS_RELEASED,
                TenantDomainSchema::RELEASED_AT => now(),
                TenantDomainSchema::COOLING_OFF_UNTIL => now()->addDays(
                    max(1, (int) config('core.tenancy.domain.cooling_off_days')),
                ),
                TenantDomainSchema::IS_PRIMARY => false,
            ]);
        } catch (Throwable $e) {
            report($e);

            throw new ReleaseFailedException;
        }

        return $domain;
    }
}
