<?php

namespace Modules\Catalog\Schemas\AttributeGroup;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class AttributeGroupTranslationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'attribute_group_translations';

    public const string ATTRIBUTE_GROUP_ID = 'attribute_group_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string TITLE = 'title';

    public const string COL_LANGUAGE = 'language';

    public const string REQ_LANGUAGE = 'language';
}
