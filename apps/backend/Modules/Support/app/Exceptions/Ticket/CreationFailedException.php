<?php

namespace Modules\Support\Exceptions\Ticket;

use Modules\Core\Exceptions\BaseException;
use Modules\Support\Exceptions\ErrorCodeEnum;
use Throwable;

class CreationFailedException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::TICKET_CREATION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
