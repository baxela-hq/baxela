<?php

namespace Modules\Menu\Exceptions\Menu;

use Modules\Core\Exceptions\BaseException;
use Modules\Menu\Exceptions\ErrorCodeEnum;
use Throwable;

class CreationFailedException extends BaseException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::MENU_CREATION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
