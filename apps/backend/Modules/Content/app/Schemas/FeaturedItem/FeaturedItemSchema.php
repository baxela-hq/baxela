<?php

namespace Modules\Content\Schemas\FeaturedItem;

use Modules\Content\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class FeaturedItemSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'featured_items';

    public const string FEATUREDABLE_TYPE = 'featuredable_type';

    public const string FEATUREDABLE_ID = 'featuredable_id';

    public const string POSITION = 'position';

    /** Morph alias stored in FEATUREDABLE_TYPE for posts. */
    public const string TYPE_POST = 'post';

    public const string RES_POST = 'post';

    public const string REQ_POST_IDS = 'post_ids';
}
