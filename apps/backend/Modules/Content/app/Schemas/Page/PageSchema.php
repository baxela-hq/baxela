<?php

namespace Modules\Content\Schemas\Page;

use Modules\Content\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class PageSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'pages';

    public const string STATUS = 'status';

    public const string RES_TRANSLATIONS = 'translations';
}
