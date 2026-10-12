<?php

namespace Modules\Notification\Transformers\User\Telegram;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notification\Models\TelegramSubscription;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;

/**
 * @mixin TelegramSubscription
 */
class TelegramSubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array. The chat id is the user's own
     * Telegram identity — it is only ever returned to its owner.
     */
    public function toArray(Request $request): array
    {
        return [
            TelegramSubscriptionSchema::ID => $this->resource->{TelegramSubscriptionSchema::ID},
            TelegramSubscriptionSchema::CHAT_ID => $this->resource->{TelegramSubscriptionSchema::CHAT_ID},
            TelegramSubscriptionSchema::USERNAME => $this->resource->{TelegramSubscriptionSchema::USERNAME},
            TelegramSubscriptionSchema::LINKED_AT => $this->resource->{TelegramSubscriptionSchema::LINKED_AT},
            TelegramSubscriptionSchema::CREATED_AT => $this->resource->{TelegramSubscriptionSchema::CREATED_AT},
        ];
    }
}
