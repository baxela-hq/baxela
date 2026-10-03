<?php

namespace Modules\Content\Schemas\PostComment;

enum PostCommentStatusEnum: string
{
    case PENDING = 'pending';

    case APPROVED = 'approved';

    case REJECTED = 'rejected';
}
