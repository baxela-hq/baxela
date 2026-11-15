<?php

namespace Modules\Catalog\Exceptions\AttributeGroup;

use Modules\Catalog\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;

class NotEmptyException extends BaseException
{
    public function __construct(
        ?string $code = null,
        int $httpStatus = 409,
        /** @var array<string, mixed> */
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::ATTRIBUTE_GROUP_NOT_EMPTY->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
