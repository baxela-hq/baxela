<?php

namespace Modules\Menu\Exceptions\MenuLink;

use Modules\Core\Exceptions\BaseException;
use Modules\Menu\Exceptions\ErrorCodeEnum;
use Throwable;

class CircularMoveException extends BaseException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        ?string $code = null,
        int $httpStatus = 422,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::LINK_CIRCULAR_MOVE->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
