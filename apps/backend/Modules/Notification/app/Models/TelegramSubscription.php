<?php

namespace Modules\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;

class TelegramSubscription extends Model
{
    protected $table = TelegramSubscriptionSchema::TABLE;

    protected $fillable = [
        TelegramSubscriptionSchema::USER_ID,
        TelegramSubscriptionSchema::CHAT_ID,
        TelegramSubscriptionSchema::USERNAME,
        TelegramSubscriptionSchema::LINKED_AT,
    ];

    protected function casts(): array
    {
        return [
            TelegramSubscriptionSchema::USER_ID => 'integer',
            TelegramSubscriptionSchema::CHAT_ID => 'integer',
            TelegramSubscriptionSchema::LINKED_AT => 'datetime',
        ];
    }
}
