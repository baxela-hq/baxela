<?php

namespace Modules\Core\Exceptions\Extension;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    /**
     * The domain error for a missing extension registry row. For enable it
     * also fires when the Composer package is absent — an extension that
     * is not present in vendor cannot be enabled, no matter what the row
     * says (the boot guard would keep it inert either way).
     */
    case EXTENSIONS_NOT_INSTALLED = 'core.extensions.not_installed';
}
