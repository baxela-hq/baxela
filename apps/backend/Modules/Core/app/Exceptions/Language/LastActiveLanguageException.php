<?php

namespace Modules\Core\Exceptions\Language;

use Modules\Core\Exceptions\BaseException;
use Throwable;

/**
 * Thrown when deactivating a language would leave the store with none —
 * at least one active language is always required.
 */
class LastActiveLanguageException extends BaseException
{
    public function __construct(
        string $code = 'core.language.last_active',
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code, $httpStatus, $meta, $isSafe, $previous);
    }
}
