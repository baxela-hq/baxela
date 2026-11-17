<?php

namespace Modules\Notification\Exceptions\NotificationPreference;

use Modules\Core\Exceptions\BaseException;
use Modules\Notification\Exceptions\ErrorCodeEnum;
use Throwable;

class LockedNotificationPreferenceException extends BaseException
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
        parent::__construct($code ?? ErrorCodeEnum::PREFERENCE_LOCKED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
