<?php

namespace Modules\Notification\Http\Controllers\Admin\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Notification\Actions\Admin\Notification\ListNotificationAction;
use Modules\Notification\Transformers\Admin\Notification\NotificationResource;

class ListNotificationController extends Controller
{
    public function __construct(protected ListNotificationAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return NotificationResource::collection($this->action->handle());
    }
}
