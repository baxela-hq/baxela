<?php

namespace Modules\Contact\Exceptions;

use Modules\Contact\Schemas\Module;
use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case CONTACT_MESSAGE_CREATION_FAILED = Module::NAME_LOWER.'.message.creation_failed';

    case CONTACT_MESSAGE_STATUS_UPDATE_FAILED = Module::NAME_LOWER.'.message.status_update_failed';

    case CONTACT_MESSAGE_DELETION_FAILED = Module::NAME_LOWER.'.message.deletion_failed';
}
