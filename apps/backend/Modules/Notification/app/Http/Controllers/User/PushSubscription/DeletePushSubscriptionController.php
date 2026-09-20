<?php

namespace Modules\Notification\Http\Controllers\User\PushSubscription;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\Notification\Actions\User\PushSubscription\DeletePushSubscriptionAction;
use Modules\Notification\Http\Requests\User\PushSubscription\PushSubscriptionRequest;

class DeletePushSubscriptionController extends Controller
{
    public function __construct(protected DeletePushSubscriptionAction $action) {}

    public function __invoke(PushSubscriptionRequest $request): Response
    {
        $this->action->handle($request->validated());

        return response()->noContent();
    }
}
