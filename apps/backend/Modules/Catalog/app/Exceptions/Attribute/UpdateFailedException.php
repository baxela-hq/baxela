<?php

namespace Modules\Catalog\Exceptions\Attribute;

use Modules\Catalog\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class UpdateFailedException extends BaseException
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
        parent::__construct($code ?? ErrorCodeEnum::ATTRIBUTE_UPDATE_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
