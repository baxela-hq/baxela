<?php

namespace Modules\Payment\Http\Controllers\Public\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payment\Actions\Public\Payment\HandleWebhookAction;

class HandleWebhookController extends Controller
{
    public function __construct(protected HandleWebhookAction $action) {}

    public function __invoke(string $driver, Request $request): JsonResponse
    {
        $this->action->handle($driver, $request);

        return response()->json(['data' => ['received' => true]]);
    }
}
