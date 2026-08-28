<?php

namespace Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use Modules\Notification\Services\Notification\NotificationService;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(NotificationService $notificationService)
    {
        $event = new UserSignedInEvent(1, 'm@m.com', now()->toDateTimeString());

        event($event);

        return UserSignedInEvent::getClassName().' Dispatched!';
    }
}
