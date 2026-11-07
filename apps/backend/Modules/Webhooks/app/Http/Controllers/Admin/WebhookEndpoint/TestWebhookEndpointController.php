<?php

namespace Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Webhooks\Actions\Webhooks\SendTestEventAction;
use Modules\Webhooks\Transformers\Admin\WebhookEndpoint\WebhookEndpointResource;

class TestWebhookEndpointController extends Controller
{
    public function __construct(protected SendTestEventAction $action) {}

    public function __invoke(Request $request, int $id): JsonResource
    {
        $this->action->handle((string) $id);

        return new WebhookEndpointResource(WebhookEndpoint::query()->findOrFail($id));
    }
}
