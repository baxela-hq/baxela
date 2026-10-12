<?php

namespace Modules\Notification\Http\Controllers\Webhook\Telegram;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Notification\Actions\Webhook\Telegram\HandleTelegramUpdateAction;

class HandleTelegramWebhookController extends Controller
{
    public function __construct(protected HandleTelegramUpdateAction $action) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->action->handleRequest($request);

        return response()->json(['data' => ['received' => true]]);
    }
}
