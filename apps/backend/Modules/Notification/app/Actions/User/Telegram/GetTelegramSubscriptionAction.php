<?php

namespace Modules\Notification\Actions\User\Telegram;

use Modules\Core\Utils\Auth;
use Modules\Notification\Models\TelegramSubscription;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;

class GetTelegramSubscriptionAction
{
    public function handle(): ?TelegramSubscription
    {
        return TelegramSubscription::query()
            ->where(TelegramSubscriptionSchema::USER_ID, Auth::id())
            ->first();
    }
}
