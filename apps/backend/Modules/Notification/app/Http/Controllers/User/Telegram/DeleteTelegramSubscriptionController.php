<?php

namespace Modules\Notification\Http\Controllers\User\Telegram;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Modules\Notification\Actions\User\Telegram\DeleteTelegramSubscriptionAction;

class DeleteTelegramSubscriptionController extends Controller
{
    public function __construct(protected DeleteTelegramSubscriptionAction $action) {}

    public function __invoke(): JsonResponse
    {
        $this->action->handle();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }
}
