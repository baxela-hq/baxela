<?php

namespace Modules\Notification\Actions\User\Telegram;

use Modules\Core\Utils\Auth;
use Modules\Notification\Models\TelegramSubscription;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;

class DeleteTelegramSubscriptionAction
{
    /**
     * Unlink the caller's own chat; rows belong to whoever linked them,
     * so no id parameter is accepted.
     */
    public function handle(): void
    {
        TelegramSubscription::query()
            ->where(TelegramSubscriptionSchema::USER_ID, Auth::id())
            ->delete();
    }
}
