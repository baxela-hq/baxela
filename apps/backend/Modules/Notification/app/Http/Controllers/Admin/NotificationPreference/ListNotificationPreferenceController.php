<?php

namespace Modules\Notification\Http\Controllers\Admin\NotificationPreference;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Notification\Actions\Admin\NotificationPreference\ListNotificationPreferenceAction;
use Modules\Notification\Transformers\Admin\NotificationPreference\NotificationPreferenceResource;

class ListNotificationPreferenceController extends Controller
{
    public function __construct(protected ListNotificationPreferenceAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return NotificationPreferenceResource::collection($this->action->handle());
    }
}
