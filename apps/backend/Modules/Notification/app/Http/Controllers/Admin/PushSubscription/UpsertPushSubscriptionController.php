<?php

namespace Modules\Notification\Http\Controllers\Admin\PushSubscription;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Modules\Notification\Actions\Admin\PushSubscription\UpsertPushSubscriptionAction;
use Modules\Notification\Http\Requests\Admin\PushSubscription\PushSubscriptionRequest;
use Modules\Notification\Transformers\Admin\PushSubscription\PushSubscriptionResource;

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
