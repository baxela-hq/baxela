<?php

namespace Modules\Notification\Http\Controllers\User\PushSubscription;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Notification\Actions\User\PushSubscription\ListPushSubscriptionsAction;
use Modules\Notification\Transformers\User\PushSubscription\PushSubscriptionResource;

class ListPushSubscriptionsController extends Controller
{
    public function __construct(protected ListPushSubscriptionsAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return PushSubscriptionResource::collection($this->action->handle());
    }
}
