<?php

namespace Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Modules\Webhooks\Models\WebhookEndpoint;
use Modules\Webhooks\Transformers\Admin\WebhookEndpoint\WebhookEndpointResource;

/**
 * Rotation = regenerate (gap-C2 D8): the old secret is invalid
 * immediately; the new one is displayed once.
 */
class RegenerateWebhookEndpointSecretController extends Controller
{
    public function __invoke(Request $request, int $id): JsonResource
    {
        $endpoint = WebhookEndpoint::query()->findOrFail($id);
        $secret = Str::random(48);

        $endpoint->forceFill(['secret' => $secret])->save();

        return WebhookEndpointResource::make($endpoint)
            ->additional(['secret' => $secret]);
    }
}
