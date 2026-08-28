<?php

namespace Modules\Catalog\Schemas\AttributeValue;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class AttributeValueTranslationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'attribute_value_translations';

    public const string ATTRIBUTE_VALUE_ID = 'attribute_value_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string TITLE = 'title';

    public const string COL_LANGUAGE = 'language';

    public const string REQ_LANGUAGE = 'language';
}
