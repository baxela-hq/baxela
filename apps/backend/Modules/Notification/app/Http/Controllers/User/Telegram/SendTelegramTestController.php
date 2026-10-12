<?php

namespace Modules\Notification\Http\Controllers\User\Telegram;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Notification\Actions\User\Telegram\SendTelegramTestAction;

class SendTelegramTestController extends Controller
{
    public function __construct(protected SendTelegramTestAction $action) {}

    public function __invoke(): JsonResponse
    {
        $this->action->handle();

        return response()->json(['data' => ['sent' => true]]);
    }
}
