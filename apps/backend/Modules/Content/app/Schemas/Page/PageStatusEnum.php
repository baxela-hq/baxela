<?php

namespace Modules\Content\Schemas\Page;

enum PageStatusEnum: string
{
    case PUBLISHED = 'published';
    case DRAFT = 'draft';
}
