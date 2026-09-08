<?php

namespace Modules\Menu\Schemas\Menu;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Menu\Schemas\Module;

class MenuTranslationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'menu_translations';

    public const string MENU_ID = 'menu_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string TITLE = 'title';

    public const string DESCRIPTION = 'description';

    public const string COL_LANGUAGE = 'language';

    public const string REQ_LANGUAGE = 'language';
}
