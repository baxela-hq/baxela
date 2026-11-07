<?php

namespace Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Webhooks\Models\WebhookEndpoint;
use Modules\Webhooks\Support\UrlGuard;
use Modules\Webhooks\Transformers\Admin\WebhookEndpoint\WebhookEndpointResource;

class UpdateWebhookEndpointController extends Controller
{
    public function __construct(protected UrlGuard $guard) {}

    public function __invoke(Request $request, int $id): JsonResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url', 'max:500'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $endpoint = WebhookEndpoint::query()->findOrFail($id);

        if (isset($validated['url'])) {
            $validated['url'] = $this->guard->pin($validated['url'])['url'];
        }

        $endpoint->update($validated);

        return new WebhookEndpointResource($endpoint);
    }
}
