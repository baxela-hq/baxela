<?php

namespace Modules\Core\Exceptions\Transfer;

use Modules\Core\Exceptions\BaseException;
use Throwable;

class ImportFailedException extends BaseException
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
        parent::__construct($code ?? ErrorCodeEnum::IMPORT_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
