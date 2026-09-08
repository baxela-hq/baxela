<?php

namespace Modules\Menu\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;
use Modules\Menu\Schemas\Module;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case MENU_CREATION_FAILED = Module::NAME_LOWER.'.menu.creation_failed';

    case MENU_UPDATE_FAILED = Module::NAME_LOWER.'.menu.update_failed';

    case MENU_DELETION_FAILED = Module::NAME_LOWER.'.menu.deletion_failed';

    case LINK_CREATION_FAILED = Module::NAME_LOWER.'.link.creation_failed';

    case LINK_UPDATE_FAILED = Module::NAME_LOWER.'.link.update_failed';

    case LINK_DELETION_FAILED = Module::NAME_LOWER.'.link.deletion_failed';

    case LINK_CIRCULAR_MOVE = Module::NAME_LOWER.'.link.circular_move';
}
