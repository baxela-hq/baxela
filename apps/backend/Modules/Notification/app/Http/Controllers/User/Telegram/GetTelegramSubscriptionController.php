<?php

namespace Modules\Notification\Http\Controllers\User\Telegram;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Notification\Actions\User\Telegram\GetTelegramSubscriptionAction;
use Modules\Notification\Transformers\User\Telegram\TelegramSubscriptionResource;

class GetTelegramSubscriptionController extends Controller
{
    public function __construct(protected GetTelegramSubscriptionAction $action) {}

    public function __invoke(): JsonResponse
    {
        $subscription = $this->action->handle();

        // Fixed shape: data is the subscription or null when unlinked.
        return response()->json([
            'data' => $subscription === null ? null : (new TelegramSubscriptionResource($subscription))->resolve(),
        ]);
    }
}
