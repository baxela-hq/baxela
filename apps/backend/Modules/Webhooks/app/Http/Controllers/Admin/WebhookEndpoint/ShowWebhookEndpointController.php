<?php

namespace Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Webhooks\Models\WebhookEndpoint;
use Modules\Webhooks\Transformers\Admin\WebhookEndpoint\WebhookEndpointResource;

class ShowWebhookEndpointController extends Controller
{
    public function __invoke(Request $request, int $id): JsonResource
    {
        return new WebhookEndpointResource(WebhookEndpoint::query()->findOrFail($id));
    }
}
