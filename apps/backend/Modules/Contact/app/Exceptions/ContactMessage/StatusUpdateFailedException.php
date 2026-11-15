<?php

namespace Modules\Contact\Exceptions\ContactMessage;

use Modules\Contact\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class StatusUpdateFailedException extends BaseException
{
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        /** @var array<string, mixed> */
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::CONTACT_MESSAGE_STATUS_UPDATE_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
