<?php

namespace Modules\Payment\Http\Controllers\Webhook\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payment\Actions\Webhook\Payment\HandleWebhookAction;

class HandleWebhookController extends Controller
{
    public function __construct(protected HandleWebhookAction $action) {}

    // Route params inject in URI order (§3.1 B2): {tenantSlug} is
    // resolved by Stage 2 before this controller runs — the context is
    // already the store, so only $driver is forwarded.
    public function __invoke(string $tenantSlug, string $driver, Request $request): JsonResponse
    {
        $this->action->handle($driver, $request);

        return response()->json(['data' => ['received' => true]]);
    }
}
