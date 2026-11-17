<?php

namespace Modules\Core\Exceptions\FailedJob;

use Modules\Core\Exceptions\BaseException;
use Throwable;

/**
 * No failed job exists for the addressed uuid.
 */
class FailedJobNotFoundException extends BaseException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        string $code = 'core.failed_jobs.not_found',
        int $httpStatus = 404,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code, $httpStatus, $meta, $isSafe, $previous);
    }
}
