<?php

namespace Modules\Contact\Schemas\ContactMessage;

enum ContactMessageStatusEnum: string
{
    case UNREAD = 'unread';

    case READ = 'read';

    case REPLIED = 'replied';

    case ARCHIVED = 'archived';
}
