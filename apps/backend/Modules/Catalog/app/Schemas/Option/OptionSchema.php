<?php

namespace Modules\Catalog\Schemas\Option;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class OptionSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'options';

    public const string POSITION = 'position';

    public const string RES_VALUES = 'values';

    public const string RES_TRANSLATIONS = 'translations';

    public const string RES_VALUES_COUNT = 'values_count';
}
