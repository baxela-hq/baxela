<?php

namespace Modules\Catalog\Schemas\AttributeValue;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class AttributeValueSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'attribute_values';

    public const string ATTRIBUTE_ID = 'attribute_id';

    public const string POSITION = 'position';

    public const string RES_ATTRIBUTE = 'attribute';

    public const string RES_TRANSLATIONS = 'translations';
}
