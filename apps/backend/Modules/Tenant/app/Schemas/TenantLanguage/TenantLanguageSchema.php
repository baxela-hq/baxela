<?php

namespace Modules\Tenant\Schemas\TenantLanguage;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Tenant\Schemas\Module;

class TenantLanguageSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'languages';

    public const string TENANT_ID = 'tenant_id';

    /** Plain id column — validated through the Core gateway. */
    public const string LANGUAGE_ID = 'language_id';
}
