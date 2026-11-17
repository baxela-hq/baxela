<?php

namespace Modules\Contact\Exceptions\ContactMessage;

use Modules\Contact\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class DeletionFailedException extends BaseException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::CONTACT_MESSAGE_DELETION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
