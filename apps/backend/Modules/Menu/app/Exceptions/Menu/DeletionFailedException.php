<?php

namespace Modules\Menu\Exceptions\Menu;

use Modules\Core\Exceptions\BaseException;
use Modules\Menu\Exceptions\ErrorCodeEnum;
use Throwable;

class DeletionFailedException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::MENU_DELETION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
