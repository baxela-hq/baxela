<?php

namespace Modules\Notification\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\Notification\Schemas\PushSubscription\PushSubscriptionSchema;

/**
 * @property int $id
 * @property int $user_id
 * @property string $endpoint
 * @property string|null $p256dh
 * @property string|null $auth
 * @property string|null $user_agent
 * @property string|null $locale
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
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
