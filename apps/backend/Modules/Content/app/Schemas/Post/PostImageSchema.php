<?php

namespace Modules\Content\Schemas\Post;

use Modules\Content\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class PostImageSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'post_images';

    public const string POST_ID = 'post_id';

    public const string MEDIA_ID = 'media_id';

    public const string URL = 'url';

    public const string COLLECTION = 'collection';

    public const string POSITION = 'position';
}
