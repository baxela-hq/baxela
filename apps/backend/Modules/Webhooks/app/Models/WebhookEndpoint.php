<?php

namespace Modules\Webhooks\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Webhooks\Schemas\WebhookEndpoint\WebhookEndpointSchema;

/**
 * A merchant-subscribed outbound webhook target (gap-C2 D1). Secrets are
 * encrypted at rest and shown once; endpoints are archived, never
 * hard-deleted, so delivery history survives.
 *
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property bool $is_active
 * @property bool $is_archived
 * @property int $consecutive_failures
 * @property int $created_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 *
 * @mixin Builder<WebhookEndpoint>
 */
class WebhookEndpoint extends Model
{
    protected $table = WebhookEndpointSchema::TABLE;

    protected $fillable = [
        WebhookEndpointSchema::NAME,
        WebhookEndpointSchema::URL,
        WebhookEndpointSchema::SECRET,
        WebhookEndpointSchema::EVENTS,
        WebhookEndpointSchema::IS_ACTIVE,
        WebhookEndpointSchema::IS_ARCHIVED,
        WebhookEndpointSchema::CONSECUTIVE_FAILURES,
        WebhookEndpointSchema::CREATED_BY,
    ];

    protected function casts(): array
    {
        return [
            WebhookEndpointSchema::SECRET => 'encrypted',
            WebhookEndpointSchema::EVENTS => 'array',
            WebhookEndpointSchema::IS_ACTIVE => 'boolean',
            WebhookEndpointSchema::IS_ARCHIVED => 'boolean',
            WebhookEndpointSchema::CONSECUTIVE_FAILURES => 'integer',
        ];
    }
}
