<?php

namespace Modules\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Notification\Schemas\PushSubscription\PushSubscriptionSchema;

class PushSubscription extends Model
{
    protected $table = PushSubscriptionSchema::TABLE;

    protected $fillable = [
        PushSubscriptionSchema::USER_ID,
        PushSubscriptionSchema::ENDPOINT,
        PushSubscriptionSchema::P256DH,
        PushSubscriptionSchema::AUTH,
        PushSubscriptionSchema::USER_AGENT,
        PushSubscriptionSchema::LOCALE,
    ];

    protected function casts(): array
    {
        return [
            PushSubscriptionSchema::USER_ID => 'integer',
        ];
    }
}
