<?php

namespace Modules\Media\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;
use Modules\Media\Schemas\Module;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case CREATION_FAILED = Module::NAME_LOWER.'.media.creation_failed';

    case FOLDER_CIRCULAR_MOVE = Module::NAME_LOWER.'.folder.circular_move';
}
