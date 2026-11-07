<?php

namespace Modules\Webhooks\Http\Controllers\Admin\WebhookDelivery;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Webhooks\Transformers\Admin\WebhookDelivery\WebhookDeliveryResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListWebhookDeliveryController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $id = WebhookDeliverySchema::TABLE.'.'.WebhookDeliverySchema::ID;

        return WebhookDeliveryResource::collection(
            QueryBuilder::for(WebhookDelivery::class)
                ->allowedFilters(
                    AllowedFilter::exact(WebhookDeliverySchema::ENDPOINT_ID),
                    AllowedFilter::exact(WebhookDeliverySchema::STATUS),
                    AllowedFilter::exact(WebhookDeliverySchema::EVENT_TYPE),
                    AllowedFilter::exact(WebhookDeliverySchema::EVENT_ID),
                )
                ->allowedSorts(WebhookDeliverySchema::CREATED_AT)
                ->select([
                    $id,
                    WebhookDeliverySchema::EVENT_ID,
                    WebhookDeliverySchema::ENDPOINT_ID,
                    WebhookDeliverySchema::EVENT_TYPE,
                    WebhookDeliverySchema::STATUS,
                    WebhookDeliverySchema::ATTEMPT_COUNT,
                    WebhookDeliverySchema::NEXT_ATTEMPT_AT,
                    WebhookDeliverySchema::RESPONSE_STATUS,
                    WebhookDeliverySchema::ERROR_CATEGORY,
                    WebhookDeliverySchema::DURATION_MS,
                    WebhookDeliverySchema::DELIVERED_AT,
                    WebhookDeliverySchema::TABLE.'.'.WebhookDeliverySchema::CREATED_AT,
                ])
                ->orderBy($id, 'desc')
                ->jsonPaginate()
        );
    }
}
