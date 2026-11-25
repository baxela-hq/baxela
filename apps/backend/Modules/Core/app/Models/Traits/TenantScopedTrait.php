<?php

namespace Modules\Core\Models\Traits;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Exceptions\TenantContextMissingException;
use Modules\Core\Exceptions\TenantOwnershipException;
use Modules\Core\Models\Scopes\TenantScope;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;

/**
 * Marks a model as store-owned (multi-tenancy §2.1): reads are filtered by
 * the request's tenant and writes are stamped with it — in every code
 * path, admin and storefront alike.
 *
 * Registered through bootTenantScopedTrait() (Laravel's trait-boot hook)
 * rather than a trait-level booted(): two models already override
 * booted(), and a class method silently replaces a same-named trait
 * method, so they would never receive the scope.
 *
 * `tenant_id` is immutable: the updating hook throws whenever it is
 * dirty. Model saves are covered here; query-builder writes skip model
 * events and are covered by the architecture allowlist and the per-engine
 * database triggers (§6.8).
 *
 * Models on the platform tier (nullable tenant_id, NULL = platform row)
 * declare `protected static bool $platformTier = true;` — only then may a
 * MODE_CENTRAL create leave the column unset.
 */
trait TenantScopedTrait
{
    /** Override to true on platform-tier models (audit_logs, settings). */
    protected static bool $platformTier = false;

    public static function bootTenantScopedTrait(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            /** @var TenantContext $context */
            $context = app(TenantContext::class);

            if ($context->mode() === TenantContext::MODE_TENANT) {
                $given = $model->getAttribute(TenantColumn::TENANT_ID);

                // An explicitly supplied id must agree with the context —
                // a request can never write a row into another store.
                if ($given !== null && (int) $given !== $context->tenantId()) {
                    throw new TenantOwnershipException($model, (int) $given, $context->tenantId());
                }

                $model->setAttribute(TenantColumn::TENANT_ID, $context->tenantId());

                return;
            }

            if ($context->mode() === TenantContext::MODE_CENTRAL && static::$platformTier) {
                // Platform-tier row written by a registered central
                // operation: the column stays NULL by design.
                return;
            }

            throw new TenantContextMissingException($model);
        });

        static::updating(function (Model $model): bool {
            if ($model->isDirty(TenantColumn::TENANT_ID)) {
                throw new TenantOwnershipException(
                    $model,
                    (int) $model->getOriginal(TenantColumn::TENANT_ID),
                    (int) $model->getAttribute(TenantColumn::TENANT_ID),
                );
            }

            return true;
        });
    }
}
