<?php

namespace Modules\Webhooks\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliveryStatusEnum;

/**
 * One endpoint's delivery of one outbox event (gap-C2 D4). The claim
 * token is the ownership unit — every state update is conditional on it,
 * so a stale worker can never overwrite a newer claim's state.
 *
 * @property string $id
 * @property int $endpoint_id
 * @property string $event_id
 * @property string $event_type
 * @property WebhookDeliveryStatusEnum $status
 * @property string|null $claim_token
 * @property CarbonInterface|null $claimed_at
 * @property int $attempt_count
 * @property list<array<string, mixed>>|null $attempts
 * @property CarbonInterface|null $next_attempt_at
 * @property int|null $response_status
 * @property string|null $response_snippet
 * @property string|null $error_category
 * @property int|null $duration_ms
 * @property CarbonInterface|null $delivered_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read WebhookEvent $event
 * @property-read WebhookEndpoint $endpoint
 *
 * @mixin Builder<WebhookDelivery>
 */
class WebhookDelivery extends Model
{
    use TenantScopedTrait;

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

    /** @return BelongsTo<WebhookEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, WebhookDeliverySchema::EVENT_ID);
    }

    /** @return BelongsTo<WebhookEndpoint, $this> */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, WebhookDeliverySchema::ENDPOINT_ID);
    }
}
