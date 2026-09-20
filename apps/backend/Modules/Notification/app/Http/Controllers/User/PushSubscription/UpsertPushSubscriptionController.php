<?php

namespace Modules\Notification\Http\Controllers\User\PushSubscription;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Modules\Notification\Actions\User\PushSubscription\UpsertPushSubscriptionAction;
use Modules\Notification\Http\Requests\User\PushSubscription\PushSubscriptionRequest;
use Modules\Notification\Transformers\User\PushSubscription\PushSubscriptionResource;

class UpsertPushSubscriptionController extends Controller
{
    public function __construct(protected UpsertPushSubscriptionAction $action) {}

    public function __invoke(PushSubscriptionRequest $request): JsonResponse
    {
        $subscription = $this->action->handle($request->validated());

        return (new PushSubscriptionResource($subscription))
            ->response()
            ->setStatusCode($subscription->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
