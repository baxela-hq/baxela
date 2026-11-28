<?php

namespace Modules\Core\Support\Tenancy;

use Modules\Core\Exceptions\CentralOperationException;
use Modules\Core\Exceptions\TenantContextMissingException;
use Modules\Core\Exceptions\TenantNotFoundException;
use Modules\Core\Exceptions\TenantOwnershipException;
use Throwable;

/**
 * F38/row 45: a `catch (Throwable)` around a store-dependent read may
 * fall back to a platform default for INFRASTRUCTURE failures only. A
 * tenancy exception means "this run is not authorized for store data"
 * and must propagate unchanged.
 */
final class TenancyExceptions
{
    public static function isTenancy(Throwable $e): bool
    {
        return $e instanceof TenantContextMissingException
            || $e instanceof TenantNotFoundException
            || $e instanceof TenantOwnershipException
            || $e instanceof CentralOperationException;
    }
}
