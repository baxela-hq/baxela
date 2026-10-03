<?php

namespace Modules\Content\Schemas\PostComment;

use Modules\Content\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class PostCommentSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'post_comments';

    public const string POST_ID = 'post_id';

    public const string USER_ID = 'user_id';

    public const string PARENT_ID = 'parent_id';

    public const string BODY = 'body';

    public const string STATUS = 'status';

    public const string RES_USER = 'user';

    public const string RES_USER_NAME = 'name';

    public const string RES_POST = 'post';

    public const string RES_REPLIES = 'replies';
}
