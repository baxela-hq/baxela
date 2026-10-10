<?php

namespace Modules\Catalog\Schemas\Brand;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class BrandSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'brands';

    public const string POSITION = 'position';

    public const string IMAGE_MEDIA_ID = 'image_media_id';

    public const string IMAGE_URL = 'image_url';

    public const string RES_TRANSLATIONS = 'translations';
}
