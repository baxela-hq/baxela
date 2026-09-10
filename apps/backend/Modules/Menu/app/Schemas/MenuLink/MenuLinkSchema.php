<?php

namespace Modules\Menu\Schemas\MenuLink;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Menu\Schemas\Module;

class MenuLinkSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'menu_links';

    public const string MENU_ID = 'menu_id';

    public const string PARENT_ID = 'parent_id';

    public const string POSITION = 'position';

    public const string URL = 'url';

    public const string TARGET = 'target';

    public const string RES_TRANSLATIONS = 'translations';

    public const string RES_CHILDREN = 'children';
}
