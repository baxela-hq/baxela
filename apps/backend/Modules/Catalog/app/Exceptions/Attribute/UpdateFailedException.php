<?php

namespace Modules\Catalog\Exceptions\Attribute;

use Modules\Catalog\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;

class UpdateFailedException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::ATTRIBUTE_UPDATE_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
