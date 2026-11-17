<?php

namespace Modules\Core\Exceptions;

use Exception;
use Throwable;

class BaseException extends Exception
{
    /**
     * @param  string|null  $code
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public $code,
        public int $httpStatus = 400,
        public array $meta = [],
        public bool $isSafe = true,
        ?Throwable $previous = null,
        /**
         * Placeholders substituted into the translated message (:name, …),
         * letting exceptions name the entity they are about.
         */
        public array $messageParams = [],
    ) {
        parent::__construct($code, 0, $previous);
    }
}
