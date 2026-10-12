<?php

namespace Modules\Notification\Gateways;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\Notification\Exceptions\NotificationException;
use Modules\Notification\Exceptions\Telegram\TelegramApiException;

/**
 * Thin SDK-less wrapper around the Telegram Bot API (plain REST). Keeps
 * HTTP out of the channel, webhook action and commands so tests only
 * ever fake the transport.
 */
class TelegramClient
{
    private const string API_BASE = 'https://api.telegram.org';

    /** Seconds before an API call is abandoned; long-polling overrides it. */
    private const int DEFAULT_TIMEOUT = 15;

    public function isConfigured(): bool
    {
        return $this->botToken() !== '';
    }

    /**
     * Bot identity — used to build the public t.me deep link.
     */
    public function getMe(): object
    {
        return $this->call('getMe')->result ?? (object) [];
    }

    /**
     * @throws TelegramApiException
     */
    public function sendMessage(int $chatId, string $text): object
    {
        return $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }

    /**
     * Point Telegram at our webhook endpoint; every update POST will then
     * carry the secret-token header the controller verifies.
     */
    public function setWebhook(string $url, string $secretToken): object
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
        ]);
    }

    public function deleteWebhook(): object
    {
        return $this->call('deleteWebhook');
    }

    /**
     * @return array<int, object> pending updates (long polling; dev only)
     */
    public function getUpdates(int $offset = 0, int $timeout = 25): array
    {
        $payload = $this->call('getUpdates', [
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => ['message', 'my_chat_member'],
        ], $timeout + 10);

        return array_values((array) ($payload->result ?? []));
    }

    private function call(string $method, array $json = [], ?int $timeout = null): object
    {
        $response = $this->request($timeout)->post($method, $json);

        if ($response->failed()) {
            $error = (array) $response->json();

            throw TelegramApiException::requestFailed(
                (int) ($error['error_code'] ?? $response->status()),
                (string) ($error['description'] ?? 'Telegram API request failed.'),
            );
        }

        return $response->object() ?? (object) [];
    }

    private function request(?int $timeout = null): PendingRequest
    {
        $token = $this->botToken();

        if ($token === '') {
            // Fail as a safe 400 instead of an opaque 401 from Telegram.
            throw NotificationException::telegramUnconfigured();
        }

        return Http::baseUrl(self::API_BASE."/bot{$token}")
            ->acceptJson()
            ->timeout($timeout ?? self::DEFAULT_TIMEOUT);
    }

    private function botToken(): string
    {
        return (string) config('notification.notifications.telegram.bot_token');
    }
}
