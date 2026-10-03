<?php

namespace Modules\Content\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case POST_CREATION_FAILED = 'content.post.creation_failed';

    case POST_UPDATE_FAILED = 'content.post.update_failed';

    case POST_CATEGORY_CREATION_FAILED = 'content.post_category.creation_failed';

    case POST_CATEGORY_UPDATE_FAILED = 'content.post_category.update_failed';

    case POST_COMMENT_CREATION_FAILED = 'content.post_comment.creation_failed';

    case POST_COMMENT_UPDATE_FAILED = 'content.post_comment.update_failed';

    case POST_COMMENT_INVALID_PARENT = 'content.post_comment.invalid_parent';
}
