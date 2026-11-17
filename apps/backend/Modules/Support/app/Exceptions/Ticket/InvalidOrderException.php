<?php

namespace Modules\Support\Exceptions\Ticket;

use Modules\Core\Exceptions\BaseException;
use Modules\Support\Exceptions\ErrorCodeEnum;
use Throwable;

class InvalidOrderException extends BaseException
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
        parent::__construct($code ?? ErrorCodeEnum::TICKET_INVALID_ORDER->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
