<?php

namespace Modules\Core\Exceptions\Transfer;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case EXPORT_MODULE_UNKNOWN = 'transfer.export.module_unknown';

    case EXPORT_FAILED = 'transfer.export.failed';

    case IMPORT_FAILED = 'transfer.import.failed';
}
