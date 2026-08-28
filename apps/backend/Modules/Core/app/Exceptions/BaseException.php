<?php

namespace Modules\Core\Exceptions;

use Exception;
use Throwable;

class BaseException extends Exception
{
    public function __construct(
        public $code,
        public int $httpStatus = 400,
        public array $meta = [],
        public bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code, 0, $previous);
    }
}
