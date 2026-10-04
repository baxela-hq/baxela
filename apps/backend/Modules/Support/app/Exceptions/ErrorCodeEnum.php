<?php

namespace Modules\Support\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;
use Modules\Support\Schemas\Module;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case TICKET_CREATION_FAILED = Module::NAME_LOWER.'.ticket.creation_failed';

    case TICKET_INVALID_ORDER = Module::NAME_LOWER.'.ticket.invalid_order';

    case TICKET_STATUS_UPDATE_FAILED = Module::NAME_LOWER.'.ticket.status_update_failed';

    case TICKET_DELETION_FAILED = Module::NAME_LOWER.'.ticket.deletion_failed';

    case TICKET_MESSAGE_CREATION_FAILED = Module::NAME_LOWER.'.ticket_message.creation_failed';
}
