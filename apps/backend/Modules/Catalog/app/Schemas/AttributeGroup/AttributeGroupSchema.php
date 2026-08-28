<?php

namespace Modules\Catalog\Schemas\AttributeGroup;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class AttributeGroupSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'attribute_groups';

    public const string POSITION = 'position';

    public const string RES_TRANSLATIONS = 'translations';

    public const string RES_ATTRIBUTES = 'attributes';

    public const string RES_ATTRIBUTES_COUNT = 'attributes_count';
}
