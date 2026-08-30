<?php

namespace Modules\Shipping\Schemas\Method;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Shipping\Schemas\Module;

class MethodTranslationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'method_translations';

    public const string METHOD_ID = 'method_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string NAME = 'name';

    public const string DESCRIPTION = 'description';

    public const string COL_LANGUAGE = 'language';

    public const string REQ_LANGUAGE = 'language';
}
