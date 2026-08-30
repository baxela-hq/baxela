<?php

namespace Modules\Shipping\Schemas\Method;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Shipping\Schemas\Module;

class MethodSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'methods';

    public const string CODE = 'code';

    public const string IS_ACTIVE = 'is_active';

    public const string POSITION = 'position';

    public const string RES_TRANSLATIONS = 'translations';
}
