<?php

namespace Modules\Webhooks\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliveryStatusEnum;

/**
 * One endpoint's delivery of one outbox event (gap-C2 D4). The claim
 * token is the ownership unit — every state update is conditional on it,
 * so a stale worker can never overwrite a newer claim's state.
 *
 * @mixin Builder
 */
class WebhookDelivery extends Model
{
    protected $table = WebhookDeliverySchema::TABLE;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        WebhookDeliverySchema::ID,
        WebhookDeliverySchema::EVENT_ID,
        WebhookDeliverySchema::ENDPOINT_ID,
        WebhookDeliverySchema::EVENT_TYPE,
        WebhookDeliverySchema::STATUS,
        WebhookDeliverySchema::CLAIM_TOKEN,
        WebhookDeliverySchema::CLAIMED_AT,
        WebhookDeliverySchema::ATTEMPTS,
        WebhookDeliverySchema::ATTEMPT_COUNT,
        WebhookDeliverySchema::NEXT_ATTEMPT_AT,
        WebhookDeliverySchema::RESPONSE_STATUS,
        WebhookDeliverySchema::RESPONSE_SNIPPET,
        WebhookDeliverySchema::ERROR_CATEGORY,
        WebhookDeliverySchema::DURATION_MS,
        WebhookDeliverySchema::DELIVERED_AT,
    ];

    protected function casts(): array
    {
        return [
            WebhookDeliverySchema::STATUS => WebhookDeliveryStatusEnum::class,
            WebhookDeliverySchema::ATTEMPTS => 'array',
            WebhookDeliverySchema::NEXT_ATTEMPT_AT => 'datetime',
            WebhookDeliverySchema::DELIVERED_AT => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WebhookDelivery $delivery): void {
            $delivery->{WebhookDeliverySchema::ID} ??= (string) Str::uuid();
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, WebhookDeliverySchema::EVENT_ID);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, WebhookDeliverySchema::ENDPOINT_ID);
    }
}
