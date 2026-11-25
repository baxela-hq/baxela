<?php

namespace Modules\Core\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * A write attempted to put a row into a store other than the request's
 * tenant, or to change an existing row's tenant (multi-tenancy §2/§6.8 —
 * `tenant_id` is immutable). Fail closed: nothing is written.
 */
class TenantOwnershipException extends BaseException
{
    public function __construct(?Model $model = null, ?int $attempted = null, ?int $expected = null)
    {
        parent::__construct(
            code: 'core.tenant.ownership_violation',
            httpStatus: 500,
            meta: array_filter([
                'model' => $model === null ? null : $model::class,
                'attempted_tenant_id' => $attempted,
                'expected_tenant_id' => $expected,
            ], static fn ($value) => $value !== null),
            isSafe: false,
        );
    }
}
