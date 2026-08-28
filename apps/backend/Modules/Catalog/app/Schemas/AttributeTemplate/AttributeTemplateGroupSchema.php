<?php

namespace Modules\Catalog\Schemas\AttributeTemplate;

use Modules\Catalog\Schemas\Module;

class AttributeTemplateGroupSchema
{
    public const string TABLE = Module::DB_PREFIX.'attribute_template_groups';

    public const string TEMPLATE_ID = 'template_id';

    public const string GROUP_ID = 'group_id';

    public const string POSITION = 'position';

    public const string REQ_GROUP_IDS = 'group_ids';
}
