<?php

namespace Modules\Webhooks\Gateways;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP wrapper for one signed webhook delivery (gap-C2 D7): the
 * connection is PINNED to the UrlGuard-validated IP via CURLOPT_RESOLVE
 * (TLS hostname verification intact), proxies are disabled (an
 * environment proxy could bypass pinning), redirects are off, a fresh
 * client per attempt (no pooled connection reuse across deliveries),
 * and the response body is capped. Tests mock this class.
 */
class WebhookDeliveryClient
{
    /**
     * @param  array<string, string>  $headers
     * @return array{status: int|null, body: string, duration_ms: int}
     *
     * @throws ConnectionException on transport failure (retryable)
     */
    public function send(
        string $url,
        string $pinnedIp,
        array $headers,
        string $body,
    ): array {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?? 443;

        $started = (int) (microtime(true) * 1000);

        $response = Http::withOptions([
            'curl' => [
                CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinnedIp}"],
                // An environment proxy would bypass the IP pin.
                CURLOPT_PROXY => '',
            ],
            'connect_timeout' => (int) config('webhooks.connect_timeout_seconds'),
            'timeout' => (int) config('webhooks.timeout_seconds'),
            // A 3xx response is a permanent failure — never followed.
            'allow_redirects' => false,
        ])
            ->withHeaders($headers)
            ->withBody($body, 'application/json')
            ->post($url);

        $durationMs = (int) (microtime(true) * 1000) - $started;
        $body = mb_substr($response->body(), 0, (int) config('webhooks.max_response_bytes'));

        return [
            'status' => $response->status(),
            'body' => $body,
            'duration_ms' => $durationMs,
        ];
    }
}
