<?php

namespace Modules\Webhooks\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Webhooks\Schemas\WebhookEvent\WebhookEventSchema;

/**
 * The transactional outbox row (gap-C2 D2): written by a synchronous
 * listener in the firing transaction, consumed by the dispatcher into
 * per-endpoint deliveries. source_key is the deterministic business
 * identity — repeated invocations of the same transition dedupe here.
 *
 * @mixin Builder
 */
class WebhookEvent extends Model
{
    protected $table = WebhookEventSchema::TABLE;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        WebhookEventSchema::ID,
        WebhookEventSchema::SOURCE_KEY,
        WebhookEventSchema::EVENT_TYPE,
        WebhookEventSchema::PAYLOAD,
        WebhookEventSchema::PROCESSED_AT,
    ];

    protected function casts(): array
    {
        return [
            WebhookEventSchema::PAYLOAD => 'array',
            WebhookEventSchema::PROCESSED_AT => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WebhookEvent $event): void {
            $event->{WebhookEventSchema::ID} ??= (string) Str::uuid();
        });
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, WebhookDeliverySchema::EVENT_ID);
    }
}
