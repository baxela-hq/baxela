<?php

namespace Modules\Notification\Http\Controllers\Admin\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Notification\Actions\Admin\Notification\MarkNotificationReadAction;
use Modules\Notification\Transformers\Admin\Notification\NotificationResource;

class MarkNotificationReadController extends Controller
{
    public function __construct(protected MarkNotificationReadAction $action) {}

    public function __invoke(Request $request, string $id): NotificationResource
    {
        return new NotificationResource($this->action->handle($id));
    }
}
