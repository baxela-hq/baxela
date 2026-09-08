<?php

namespace Modules\Contact\Exceptions\ContactMessage;

use Modules\Contact\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class CreationFailedException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::CONTACT_MESSAGE_CREATION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
