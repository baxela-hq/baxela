<?php

namespace Modules\Notification\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\Notification\Schemas\Notification\NotificationAudienceEnum;
use Modules\Notification\Schemas\Notification\NotificationChannelEnum;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationPreferenceSchema;

/**
 * @property int $id
 * @property int $user_id
 * @property NotificationCodeEnum $code
 * @property NotificationAudienceEnum $audience
 * @property NotificationChannelEnum $channel
 * @property bool $is_enabled
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class NotificationPreference extends Model
{
    protected $table = NotificationPreferenceSchema::TABLE;

    protected $fillable = [
        NotificationPreferenceSchema::USER_ID,
        NotificationPreferenceSchema::CODE,
        NotificationPreferenceSchema::AUDIENCE,
        NotificationPreferenceSchema::CHANNEL,
        NotificationPreferenceSchema::IS_ENABLED,
    ];

    protected function casts(): array
    {
        return [
            NotificationPreferenceSchema::CODE => NotificationCodeEnum::class,
            NotificationPreferenceSchema::AUDIENCE => NotificationAudienceEnum::class,
            NotificationPreferenceSchema::CHANNEL => NotificationChannelEnum::class,
            NotificationPreferenceSchema::IS_ENABLED => 'boolean',
        ];
    }
}
