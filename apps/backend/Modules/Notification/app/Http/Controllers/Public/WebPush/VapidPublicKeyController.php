<?php

namespace Modules\Notification\Http\Controllers\Public\WebPush;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Notification\Schemas\PushSubscription\PushSubscriptionSchema;

class VapidPublicKeyController extends Controller
{
    /**
     * The VAPID public key browsers need before subscribing. Null when
     * web push is not configured — clients treat that as unsupported.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                PushSubscriptionSchema::PUBLIC_KEY => config('notification.notifications.webpush.vapid.public_key'),
            ],
        ]);
    }
}
