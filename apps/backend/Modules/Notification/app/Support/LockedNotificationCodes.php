<?php

namespace Modules\Notification\Support;

use Modules\Notification\Schemas\Notification\NotificationCodeEnum;

/**
 * Transactional codes that must always reach the recipient — security and
 * account-lifecycle notifications. Never preference-filtered at dispatch
 * time and never toggleable through the preference endpoints.
 */
final class LockedNotificationCodes
{
    public const array CODES = [
        NotificationCodeEnum::AUTH_USER_OTP_CODE,
        NotificationCodeEnum::AUTH_USER_EMAIL_VERIFIED,
        // S.4: the new-device alert is the recipient's "was this you?"
        // signal — it must never be disableable (F8).
        NotificationCodeEnum::AUTH_USER_NEW_DEVICE,
    ];

    public static function contains(NotificationCodeEnum $code): bool
    {
        return in_array($code, self::CODES, true);
    }
}
