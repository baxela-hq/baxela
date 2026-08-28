<?php

namespace Modules\Catalog\Schemas\Attribute;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class AttributeSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'attributes';

    public const string GROUP_ID = 'group_id';

    public const string CODE = 'code';

    public const string DATA_TYPE = 'data_type';

    public const string IS_FILTERABLE = 'is_filterable';

    public const string POSITION = 'position';

    public const string RES_GROUP = 'group';

    public const string RES_TRANSLATIONS = 'translations';

    public const string RES_VALUES = 'values';

    public const string RES_VALUES_COUNT = 'values_count';

    public const string RES_CATEGORIES = 'categories';
}
