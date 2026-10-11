<?php

namespace Modules\Notification\Actions\Admin\NotificationPreference;

use Modules\Core\Utils\Auth;
use Modules\Notification\Exceptions\NotificationPreference\LockedNotificationPreferenceException;
use Modules\Notification\Models\NotificationPreference;
use Modules\Notification\Schemas\Notification\NotificationAudienceEnum;
use Modules\Notification\Schemas\Notification\NotificationChannelEnum;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationPreferenceSchema;
use Modules\Notification\Support\LockedNotificationCodes;
use Modules\Notification\Support\PreferenceMatrix;

class UpdateNotificationPreferenceAction
{
    /**
     * Upsert the caller's override for one code/channel pair and return
     * the recomputed merged entry.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws LockedNotificationPreferenceException
     */
    public function handle(array $data): array
    {
        $code = NotificationCodeEnum::from($data[NotificationPreferenceSchema::CODE]);

        if (LockedNotificationCodes::contains($code)) {
            throw new LockedNotificationPreferenceException;
        }

        NotificationPreference::query()->updateOrCreate(
            [
                NotificationPreferenceSchema::USER_ID => Auth::id(),
                NotificationPreferenceSchema::AUDIENCE => NotificationAudienceEnum::ADMIN->value,
                NotificationPreferenceSchema::CODE => $code,
                NotificationPreferenceSchema::CHANNEL => NotificationChannelEnum::from($data[NotificationPreferenceSchema::CHANNEL]),
            ],
            [
                NotificationPreferenceSchema::IS_ENABLED => $data[NotificationPreferenceSchema::IS_ENABLED],
            ]
        );

        return PreferenceMatrix::entryFor(NotificationAudienceEnum::ADMIN, Auth::id(), $code);
    }
}
