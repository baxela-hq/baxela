<?php

namespace Modules\Core\Exceptions\Language;

use Modules\Core\Exceptions\BaseException;
use Throwable;

/**
 * Thrown when an admin tries to activate a language whose locale files do
 * not ship with this release (see the core.implemented_locales config) —
 * the storefront cannot route what has no translations.
 */
class LanguageNotImplementedException extends BaseException
{
    public function __construct(
        string $code = 'core.language.not_implemented',
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code, $httpStatus, $meta, $isSafe, $previous);
    }
}
