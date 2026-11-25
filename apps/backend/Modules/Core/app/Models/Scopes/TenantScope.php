<?php

namespace Modules\Core\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Core\Exceptions\TenantContextMissingException;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;

/**
 * The fail-closed store scope (multi-tenancy §2). A missing tenant
 * context throws instead of silently querying unscoped — the deliberate
 * difference from UserScope, which narrows to `user_id IS NULL`.
 *
 * @implements Scope<Model>
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->mode() === TenantContext::MODE_CENTRAL) {
            // Explicit, authorized cross-store operation.
            return;
        }

        if ($context->mode() !== TenantContext::MODE_TENANT) {
            throw new TenantContextMissingException($model);
        }

        $builder->where($model->qualifyColumn(TenantColumn::TENANT_ID), $context->tenantId());
    }
}
