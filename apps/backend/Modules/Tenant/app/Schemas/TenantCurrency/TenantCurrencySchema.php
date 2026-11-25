<?php

namespace Modules\Tenant\Schemas\TenantCurrency;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Tenant\Schemas\Module;

class TenantCurrencySchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'currencies';

    public const string TENANT_ID = 'tenant_id';

    /** Plain id column — validated through the Core gateway. */
    public const string CURRENCY_ID = 'currency_id';
}
