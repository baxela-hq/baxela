<?php

namespace Modules\Notification\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Notification\Database\Factories\NotificationFactory;
use Modules\Notification\Schemas\Notification\NotificationAudienceEnum;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationSchema;

/**
 * @property int $id
 * @property int|null $user_id
 * @property NotificationCodeEnum $code
 * @property NotificationAudienceEnum $audience
 * @property string $title
 * @property string $body
 * @property array<string, mixed>|null $meta
 * @property CarbonInterface|null $read_at
 * @property CarbonInterface $created_at
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    use TenantScopedTrait;

    protected $table = NotificationSchema::TABLE;

    public $timestamps = false;

    protected $fillable = [
        NotificationSchema::USER_ID,
        NotificationSchema::CODE,
        NotificationSchema::AUDIENCE,
        NotificationSchema::TITLE,
        NotificationSchema::BODY,
        NotificationSchema::META,
        NotificationSchema::READ_AT,
    ];

    protected function casts(): array
    {
        return [
            NotificationSchema::CODE => NotificationCodeEnum::class,
            NotificationSchema::AUDIENCE => NotificationAudienceEnum::class,
            NotificationSchema::READ_AT => 'datetime',
            NotificationSchema::META => 'array',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    protected static function newFactory(): NotificationFactory
    {
        return NotificationFactory::new();
    }
}
