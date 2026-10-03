<?php

namespace Modules\Content\Schemas\Post;

enum PostStatusEnum: string
{
    case PUBLISHED = 'published';

    case DRAFT = 'draft';
}
