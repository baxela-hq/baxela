<?php

namespace Modules\Notification\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;

/**
 * @property int $id
 * @property int $user_id
 * @property int $chat_id
 * @property string|null $username
 * @property CarbonInterface|null $linked_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
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
