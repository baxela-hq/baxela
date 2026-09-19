<?php

namespace Modules\Notification\Http\Controllers\Admin\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Notification\Actions\Admin\Notification\GetUnreadNotificationCountAction;
use Modules\Notification\Transformers\Admin\Notification\UnreadCountResource;

class GetUnreadNotificationCountController extends Controller
{
    public function __construct(protected GetUnreadNotificationCountAction $action) {}

    public function __invoke(Request $request): UnreadCountResource
    {
        return new UnreadCountResource($this->action->handle());
    }
}
