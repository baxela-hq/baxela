<?php

namespace Modules\Tenant\Actions\Admin\TenantDomain;

use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;
use Modules\Core\Http\Middleware\NormalizeTenantRequest;
use Modules\Core\Support\Tenancy\CentralOperations;
use Modules\Tenant\Exceptions\TenantDomain\AlreadyAttachedException;
use Modules\Tenant\Exceptions\TenantDomain\AttachmentFailedException;
use Modules\Tenant\Exceptions\TenantDomain\CoolingOffException;
use Modules\Tenant\Exceptions\TenantDomain\InUseException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantDomain;
use Modules\Tenant\Operations\AttachTenantDomainOperation;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;
use Throwable;

/**
 * Attaches a domain to a store — a platform operation (multi-tenancy §6.5).
 * The row is created pending: it stays inert, resolving nothing, until the
 * domain is verified (§9.7). A domain held by another store is refused, and
 * so is one still inside its cooling-off window after a release.
 *
 * handle() is the operation's only authorized caller; execute() is its
 * registered handler.
 */
class AttachTenantDomainAction
{
    public function __construct(private readonly CentralOperations $central) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(string $id, array $data): TenantDomain
    {
        $operation = new AttachTenantDomainOperation(
            (int) $id,
            (string) ($data[TenantDomainSchema::DOMAIN] ?? ''),
            (bool) ($data[TenantDomainSchema::IS_PRIMARY] ?? false),
        );

        $result = $this->central->run($operation, static::class);

        return $result instanceof TenantDomain
            ? $result
            : throw new LogicException('The tenant-domain.attach operation did not return a domain.');
    }

    /** Runs inside CentralOperations::run(). */
    public function execute(AttachTenantDomainOperation $operation): TenantDomain
    {
        $domain = NormalizeTenantRequest::normalizeHost($operation->domain);

        if ($domain === null) {
            throw new AttachmentFailedException;
        }

        Tenant::query()->findOrFail($operation->tenantId);

        $this->assertAttachable($operation->tenantId, $domain);

        try {
            return TenantDomain::query()->create([
                TenantDomainSchema::TENANT_ID => $operation->tenantId,
                TenantDomainSchema::DOMAIN => $domain,
                TenantDomainSchema::STATUS => TenantDomainSchema::STATUS_PENDING,
                TenantDomainSchema::IS_PRIMARY => $this->isPrimary($operation),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another store took the domain between the check and the insert.
            throw new InUseException;
        } catch (Throwable $e) {
            report($e);

            throw new AttachmentFailedException;
        }
    }

    private function assertAttachable(int $tenantId, string $domain): void
    {
        // A released row keeps the domain for its cooling-off window; every
        // other row holds it outright.
        $holders = TenantDomain::query()->where(TenantDomainSchema::DOMAIN, $domain)->get();

        foreach ($holders as $holder) {
            $holderTenantId = (int) $holder->{TenantDomainSchema::TENANT_ID};
            $released = $holder->{TenantDomainSchema::STATUS} === TenantDomainSchema::STATUS_RELEASED;

            if (! $released) {
                throw $holderTenantId === $tenantId ? new AlreadyAttachedException : new InUseException;
            }

            $coolingOffUntil = $holder->{TenantDomainSchema::COOLING_OFF_UNTIL};

            if ($holderTenantId !== $tenantId && $coolingOffUntil !== null && $coolingOffUntil->isFuture()) {
                throw new CoolingOffException(meta: ['cooling_off_until' => $coolingOffUntil->toIso8601String()]);
            }
        }
    }

    /**
     * Primary only when the store has none yet: switching the primary of a
     * store that already serves one belongs to domain verification (§9.7).
     */
    private function isPrimary(AttachTenantDomainOperation $operation): bool
    {
        $hasPrimary = TenantDomain::query()
            ->where(TenantDomainSchema::TENANT_ID, $operation->tenantId)
            ->where(TenantDomainSchema::IS_PRIMARY, true)
            ->exists();

        return $operation->isPrimary && ! $hasPrimary;
    }
}
