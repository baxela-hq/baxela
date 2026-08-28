<?php

namespace Modules\Catalog\Exceptions\AttributeValue;

use Modules\Catalog\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;

class CreationFailedException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::ATTRIBUTE_VALUE_CREATION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
