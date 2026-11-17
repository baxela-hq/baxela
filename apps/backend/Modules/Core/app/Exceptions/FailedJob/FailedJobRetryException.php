<?php

namespace Modules\Core\Exceptions\FailedJob;

use Modules\Core\Exceptions\BaseException;
use Throwable;

/**
 * The retry command ran but the failed row survived it — an unexpected queue
 * state that the operator should not see a success response for. Unsafe: the
 * internal detail is logged, the client gets the generic 500.
 */
class FailedJobRetryException extends BaseException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        string $code = 'core.failed_jobs.retry_failed',
        int $httpStatus = 500,
        array $meta = [],
        bool $isSafe = false,
        ?Throwable $previous = null
    ) {
        parent::__construct($code, $httpStatus, $meta, $isSafe, $previous);
    }
}
