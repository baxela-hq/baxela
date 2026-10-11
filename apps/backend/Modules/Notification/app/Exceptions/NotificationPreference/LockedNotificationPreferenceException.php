<?php

namespace Modules\Notification\Exceptions\NotificationPreference;

use Modules\Core\Exceptions\BaseException;
use Modules\Notification\Exceptions\ErrorCodeEnum;
use Throwable;

class LockedNotificationPreferenceException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::PREFERENCE_LOCKED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
