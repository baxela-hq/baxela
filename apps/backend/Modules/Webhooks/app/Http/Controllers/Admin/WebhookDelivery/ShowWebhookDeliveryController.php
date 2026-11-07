<?php

namespace Modules\Webhooks\Http\Controllers\Admin\WebhookDelivery;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Transformers\Admin\WebhookDelivery\WebhookDeliveryResource;

class ShowWebhookDeliveryController extends Controller
{
    public function __invoke(Request $request, string $id): JsonResource
    {
        return new WebhookDeliveryResource(WebhookDelivery::query()->findOrFail($id));
    }
}
