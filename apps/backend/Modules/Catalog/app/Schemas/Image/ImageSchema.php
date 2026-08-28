<?php

namespace Modules\Catalog\Schemas\Image;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class ImageSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'images';

    public const string PRODUCT_ID = 'product_id';

    public const string VARIANT_ID = 'variant_id';

    public const string MEDIA_ID = 'media_id';

    public const string URL = 'url';

    public const string COLLECTION = 'collection';

    public const string POSITION = 'position';
}
