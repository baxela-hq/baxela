<?php

namespace Modules\Core\Exceptions\Language;

use Modules\Core\Exceptions\BaseException;
use Throwable;

/**
 * Thrown when an admin tries to deactivate the store's default language —
 * switch the store default (Settings) first, then deactivate.
 */
class CannotDeactivateDefaultLanguageException extends BaseException
{
    public function __construct(
        string $code = 'core.language.cannot_deactivate_default',
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code, $httpStatus, $meta, $isSafe, $previous);
    }
}
