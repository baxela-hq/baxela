<?php

namespace Modules\Catalog\Schemas\ProductComment;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class ProductCommentSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'product_comments';

    public const string PRODUCT_ID = 'product_id';

    public const string USER_ID = 'user_id';

    public const string PARENT_ID = 'parent_id';

    public const string BODY = 'body';

    public const string STATUS = 'status';

    public const string RES_USER = 'user';

    public const string RES_USER_NAME = 'name';

    public const string RES_PRODUCT = 'product';

    public const string RES_REPLIES = 'replies';
}
