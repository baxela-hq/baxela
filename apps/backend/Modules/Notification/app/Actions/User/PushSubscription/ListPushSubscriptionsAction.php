<?php

namespace Modules\Notification\Actions\User\PushSubscription;

use Modules\Core\Utils\Auth;
use Modules\Notification\Models\PushSubscription;
use Modules\Notification\Schemas\PushSubscription\PushSubscriptionSchema;

class ListPushSubscriptionsAction
{
    public function handle()
    {
        return PushSubscription::query()
            ->where(PushSubscriptionSchema::USER_ID, Auth::id())
            ->orderBy(PushSubscriptionSchema::ID)
            ->get();
    }
}
