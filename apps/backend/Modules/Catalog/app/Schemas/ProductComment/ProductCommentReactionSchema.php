<?php

namespace Modules\Catalog\Schemas\ProductComment;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class ProductCommentReactionSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'product_comment_reactions';

    public const string PRODUCT_COMMENT_ID = 'product_comment_id';

    public const string USER_ID = 'user_id';

    public const string REACTION = 'reaction';

    public const string RELATION_COMMENT = 'comment';

    /** Virtual output key: reactions are addressed by their comment in the API. */
    public const string RES_COMMENT_ID = 'comment_id';
}
