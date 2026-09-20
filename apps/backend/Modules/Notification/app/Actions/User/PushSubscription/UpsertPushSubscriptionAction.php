<?php

namespace Modules\Notification\Actions\User\PushSubscription;

use Modules\Core\Utils\Auth;
use Modules\Notification\Models\PushSubscription;
use Modules\Notification\Schemas\PushSubscription\PushSubscriptionSchema;

class UpsertPushSubscriptionAction
{
    /**
     * Register (or refresh) the caller's browser subscription. Keyed by
     * endpoint so re-subscribing on a shared browser re-assigns the row
     * to whoever just signed in.
     */
    public function handle(array $data): PushSubscription
    {
        return PushSubscription::query()->updateOrCreate(
            [PushSubscriptionSchema::ENDPOINT => $data[PushSubscriptionSchema::ENDPOINT]],
            [
                PushSubscriptionSchema::USER_ID => Auth::id(),
                PushSubscriptionSchema::P256DH => $data[PushSubscriptionSchema::KEYS][PushSubscriptionSchema::P256DH] ?? null,
                PushSubscriptionSchema::AUTH => $data[PushSubscriptionSchema::KEYS][PushSubscriptionSchema::AUTH] ?? null,
                PushSubscriptionSchema::USER_AGENT => $data[PushSubscriptionSchema::USER_AGENT] ?? null,
                PushSubscriptionSchema::LOCALE => $data[PushSubscriptionSchema::LOCALE] ?? null,
            ],
        );
    }
}
