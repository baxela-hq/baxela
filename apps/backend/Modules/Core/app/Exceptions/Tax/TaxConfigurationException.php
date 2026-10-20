<?php

namespace Modules\Core\Exceptions\Tax;

use Modules\Core\Exceptions\BaseException;
use Throwable;

/**
 * Thrown by the tax calculator when the configured data cannot produce a
 * trustworthy number (dangling tax-class reference, missing default
 * class). Deliberately loud: a broken reference must never degrade into a
 * silent zero-tax order. Callers let it bubble — the distinct code
 * (`tax.configuration.*`) tells an operator this is configuration, not a
 * customer error.
 */
class TaxConfigurationException extends BaseException
{
    public function __construct(
        string $code = 'tax.configuration.invalid_class',
        int $httpStatus = 500,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code, $httpStatus, $meta, $isSafe, $previous);
    }
}
