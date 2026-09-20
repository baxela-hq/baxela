<?php

namespace Modules\Notification\Actions\Admin\PushSubscription;

use Modules\Core\Utils\Auth;
use Modules\Notification\Models\PushSubscription;
use Modules\Notification\Schemas\PushSubscription\PushSubscriptionSchema;

class DeletePushSubscriptionAction
{
    public function handle(array $data): int
    {
        return PushSubscription::query()
            ->where(PushSubscriptionSchema::USER_ID, Auth::id())
            ->where(PushSubscriptionSchema::ENDPOINT, $data[PushSubscriptionSchema::ENDPOINT])
            ->delete();
    }
}
