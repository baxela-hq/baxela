<?php

namespace Modules\Catalog\Schemas\Attribute;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class AttributeTranslationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'attribute_translations';

    public const string ATTRIBUTE_ID = 'attribute_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string TITLE = 'title';

    public const string COL_LANGUAGE = 'language';

    public const string REQ_LANGUAGE = 'language';
}
