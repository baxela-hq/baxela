<?php

namespace Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Webhooks\Models\WebhookEndpoint;
use Modules\Webhooks\Schemas\WebhookEndpoint\WebhookEndpointSchema;

/**
 * Archive-not-delete (gap-C2 D12): delivery history survives; no new
 * deliveries are created for archived endpoints.
 */
class DeleteWebhookEndpointController extends Controller
{
    public function __invoke(Request $request, int $id): Response
    {
        WebhookEndpoint::query()->findOrFail($id)
            ->forceFill([WebhookEndpointSchema::IS_ARCHIVED => true])
            ->save();

        return response()->noContent();
    }
}
