<?php

namespace Modules\Shipping\Exceptions\Shipment;

use Modules\Core\Exceptions\BaseException;
use Modules\Shipping\Exceptions\ErrorCodeEnum;
use Throwable;

class AlreadyExistsException extends BaseException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        ?string $code = null,
        int $httpStatus = 409,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::SHIPMENT_ALREADY_EXISTS->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
