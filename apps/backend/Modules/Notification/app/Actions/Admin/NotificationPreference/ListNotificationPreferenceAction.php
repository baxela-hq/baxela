<?php

namespace Modules\Notification\Actions\Admin\NotificationPreference;

use Modules\Core\Utils\Auth;
use Modules\Notification\Schemas\Notification\NotificationAudienceEnum;
use Modules\Notification\Support\PreferenceMatrix;

class ListNotificationPreferenceAction
{
    /**
     * Every admin-configurable notification merged with the caller's
     * channel overrides.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(): array
    {
        return PreferenceMatrix::entries(NotificationAudienceEnum::ADMIN, Auth::id());
    }
}
