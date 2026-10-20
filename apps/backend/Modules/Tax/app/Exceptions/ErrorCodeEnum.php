<?php

namespace Modules\Tax\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case CLASS_CREATION_FAILED = 'tax.class.creation_failed';

    case CLASS_UPDATE_FAILED = 'tax.class.update_failed';

    case CLASS_DELETE_DEFAULT = 'tax.class.delete_default';

    case CLASS_IN_USE = 'tax.class.in_use';

    case RATE_CREATION_FAILED = 'tax.rate.creation_failed';

    case RATE_UPDATE_FAILED = 'tax.rate.update_failed';
}
