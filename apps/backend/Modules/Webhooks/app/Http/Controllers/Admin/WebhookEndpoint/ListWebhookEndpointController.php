<?php

namespace Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Webhooks\Models\WebhookEndpoint;
use Modules\Webhooks\Schemas\WebhookEndpoint\WebhookEndpointSchema;
use Modules\Webhooks\Transformers\Admin\WebhookEndpoint\WebhookEndpointResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListWebhookEndpointController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $id = WebhookEndpointSchema::TABLE.'.'.WebhookEndpointSchema::ID;

        return WebhookEndpointResource::collection(
            QueryBuilder::for(WebhookEndpoint::class)
                ->allowedFilters(
                    AllowedFilter::exact(WebhookEndpointSchema::IS_ACTIVE),
                )
                // Archived endpoints stay out of the lists; history lives
                // in the deliveries.
                ->where(WebhookEndpointSchema::IS_ARCHIVED, false)
                ->allowedSorts(WebhookEndpointSchema::ID)
                ->select([
                    $id,
                    WebhookEndpointSchema::NAME,
                    WebhookEndpointSchema::URL,
                    WebhookEndpointSchema::EVENTS,
                    WebhookEndpointSchema::IS_ACTIVE,
                    WebhookEndpointSchema::IS_ARCHIVED,
                    WebhookEndpointSchema::CONSECUTIVE_FAILURES,
                    WebhookEndpointSchema::TABLE.'.'.WebhookEndpointSchema::CREATED_AT,
                    WebhookEndpointSchema::TABLE.'.'.WebhookEndpointSchema::UPDATED_AT,
                ])
                ->orderBy($id, 'desc')
                ->jsonPaginate()
        );
    }
}
