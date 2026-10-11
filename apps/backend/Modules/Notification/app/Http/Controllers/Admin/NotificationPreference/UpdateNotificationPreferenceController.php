<?php

namespace Modules\Notification\Http\Controllers\Admin\NotificationPreference;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Notification\Actions\Admin\NotificationPreference\UpdateNotificationPreferenceAction;
use Modules\Notification\Http\Requests\Admin\NotificationPreference\NotificationPreferenceRequest;
use Modules\Notification\Transformers\Admin\NotificationPreference\NotificationPreferenceResource;

class UpdateNotificationPreferenceController extends Controller
{
    public function __construct(protected UpdateNotificationPreferenceAction $action) {}

    public function __invoke(NotificationPreferenceRequest $request): JsonResponse
    {
        return (new NotificationPreferenceResource($this->action->handle($request->validated())))
            ->response();
    }
}
