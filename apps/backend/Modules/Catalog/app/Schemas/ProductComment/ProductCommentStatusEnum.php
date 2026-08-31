<?php

namespace Modules\Catalog\Schemas\ProductComment;

enum ProductCommentStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
