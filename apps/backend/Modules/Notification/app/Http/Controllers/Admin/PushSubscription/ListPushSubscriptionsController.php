<?php

namespace Modules\Notification\Http\Controllers\Admin\PushSubscription;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Notification\Actions\Admin\PushSubscription\ListPushSubscriptionsAction;
use Modules\Notification\Transformers\Admin\PushSubscription\PushSubscriptionResource;

class ListPushSubscriptionsController extends Controller
{
    public function __construct(protected ListPushSubscriptionsAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return PushSubscriptionResource::collection($this->action->handle());
    }
}
