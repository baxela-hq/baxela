<?php

namespace Modules\Notification\Http\Controllers\User\Telegram;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Modules\Notification\Actions\User\Telegram\LinkTelegramAction;
use Modules\Notification\Http\Requests\User\Telegram\LinkTelegramRequest;
use Modules\Notification\Transformers\User\Telegram\TelegramSubscriptionResource;

class LinkTelegramController extends Controller
{
    public function __construct(protected LinkTelegramAction $action) {}

    public function __invoke(LinkTelegramRequest $request): JsonResponse
    {
        $subscription = $this->action->handle($request->validated());

        return (new TelegramSubscriptionResource($subscription))
            ->response()
            ->setStatusCode($subscription->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
