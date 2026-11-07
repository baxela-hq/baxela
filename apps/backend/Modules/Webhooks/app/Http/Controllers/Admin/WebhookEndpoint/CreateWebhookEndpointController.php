<?php

namespace Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Modules\Core\Utils\Auth;
use Modules\Webhooks\Http\Requests\Admin\WebhookEndpoint\WebhookEndpointRequest;
use Modules\Webhooks\Models\WebhookEndpoint;
use Modules\Webhooks\Schemas\WebhookEndpoint\WebhookEndpointSchema;
use Modules\Webhooks\Support\UrlGuard;
use Modules\Webhooks\Transformers\Admin\WebhookEndpoint\WebhookEndpointResource;
use Symfony\Component\HttpFoundation\Response;

class CreateWebhookEndpointController extends Controller
{
    public function __construct(protected UrlGuard $guard) {}

    public function __invoke(WebhookEndpointRequest $request): JsonResponse
    {
        // SSRF validation at the boundary (gap-C2 D7): a URL that cannot
        // be safely delivered is rejected here, before any row exists.
        ['url' => $validatedUrl] = $this->guard->pin($request->validated()['url']);

        $secret = Str::random(48);

        $endpoint = WebhookEndpoint::query()->create([
            ...$request->validated(),
            WebhookEndpointSchema::URL => $validatedUrl,
            WebhookEndpointSchema::SECRET => $secret,
            WebhookEndpointSchema::CREATED_BY => Auth::id(),
        ]);

        // The plaintext secret is shown EXACTLY ONCE (gap-C2 D8).
        return WebhookEndpointResource::make($endpoint)
            ->additional(['secret' => $secret])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
