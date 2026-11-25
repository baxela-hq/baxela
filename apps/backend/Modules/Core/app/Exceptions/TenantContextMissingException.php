<?php

namespace Modules\Core\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * A tenant-scoped query ran without a tenant context. This is a
 * programming or deployment bug, never a client error: the scope refuses
 * to query unscoped instead of silently widening (multi-tenancy §2).
 */
class TenantContextMissingException extends BaseException
{
    public function __construct(?Model $model = null)
    {
        parent::__construct(
            code: 'core.tenant.context_missing',
            httpStatus: 500,
            meta: $model === null ? [] : ['model' => $model::class],
            isSafe: false,
        );
    }
}
