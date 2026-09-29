<?php

namespace Modules\Catalog\Exceptions\Product;

use Modules\Catalog\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;

class ImportFailedException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::PRODUCT_IMPORT_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
