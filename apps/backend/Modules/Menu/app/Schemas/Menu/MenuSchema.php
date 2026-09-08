<?php

namespace Modules\Menu\Schemas\Menu;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Menu\Schemas\Module;

class MenuSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'menus';

    public const string LOCATION = 'location';

    public const string IS_ACTIVE = 'is_active';

    public const string RES_TRANSLATIONS = 'translations';

    public const string RES_LINKS = 'links';
}
