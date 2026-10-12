<?php

namespace Modules\Notification\Http\Controllers\Public\Telegram;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Modules\Notification\Exceptions\Telegram\TelegramApiException;
use Modules\Notification\Gateways\TelegramClient;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;

class BotInfoController extends Controller
{
    /**
     * Public bot identity so both apps can deep-link users into the
     * Telegram chat ("open bot", t.me/<username>). Nulls mean Telegram
     * is not configured — clients treat that as feature-unavailable,
     * same as a missing VAPID key.
     */
    public function __invoke(TelegramClient $telegram): JsonResponse
    {
        $username = null;

        if ($telegram->isConfigured()) {
            $username = Cache::remember(
                'telegram:bot:username',
                now()->addHour(),
                function () use ($telegram): ?string {
                    try {
                        return $telegram->getMe()->username ?? null;
                    } catch (TelegramApiException) {
                        return null;
                    }
                }
            );
        }

        return response()->json([
            'data' => [
                TelegramSubscriptionSchema::BOT_USERNAME => $username,
                TelegramSubscriptionSchema::BOT_URL => $username === null ? null : "https://t.me/{$username}",
            ],
        ]);
    }
}
