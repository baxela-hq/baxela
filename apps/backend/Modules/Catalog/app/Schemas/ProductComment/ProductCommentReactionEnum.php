<?php

namespace Modules\Catalog\Schemas\ProductComment;

enum ProductCommentReactionEnum: string
{
    case LIKE = 'like';

    case DISLIKE = 'dislike';
}
