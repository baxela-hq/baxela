<?php

namespace Modules\Shipping\Exceptions\Shipment;

use Modules\Core\Exceptions\BaseException;
use Modules\Shipping\Exceptions\ErrorCodeEnum;
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
        parent::__construct($code ?? ErrorCodeEnum::SHIPMENT_CREATION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
