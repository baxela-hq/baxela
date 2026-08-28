<?php

namespace Modules\Catalog\Schemas\Category;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class CategorySchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'categories';

    public const string PARENT_ID = 'parent_id';

    public const string POSITION = 'position';

    public const string RES_TRANSLATIONS = 'translations';

    public const string RES_ATTRIBUTES = 'attributes';
}
