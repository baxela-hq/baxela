<?php

namespace Modules\Catalog\Schemas\AttributeTemplate;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class AttributeTemplateSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'attribute_templates';

    public const string TITLE = 'title';

    public const string DESCRIPTION = 'description';

    public const string IS_ACTIVE = 'is_active';

    public const string POSITION = 'position';

    public const string RES_GROUPS = 'groups';

    public const string RES_GROUPS_COUNT = 'groups_count';
}
