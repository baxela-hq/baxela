<?php

namespace Modules\Notification\Http\Controllers\User\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Notification\Actions\User\Notification\GetUnreadNotificationCountAction;
use Modules\Notification\Transformers\User\Notification\UnreadCountResource;

class GetUnreadNotificationCountController extends Controller
{
    public function __construct(protected GetUnreadNotificationCountAction $action) {}

    public function __invoke(Request $request): UnreadCountResource
    {
        return new UnreadCountResource($this->action->handle());
    }
}
