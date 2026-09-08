<?php

namespace Modules\Menu\Schemas\MenuLink;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Menu\Schemas\Module;

class MenuLinkTranslationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'menu_link_translations';

    public const string MENU_LINK_ID = 'menu_link_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string TITLE = 'title';

    public const string DESCRIPTION = 'description';

    public const string COL_LANGUAGE = 'language';

    public const string REQ_LANGUAGE = 'language';
}
