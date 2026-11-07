<?php

namespace Modules\Webhooks\Support;

/**
 * Canonical outbound signing (gap-C2 D8): the timestamp, event id,
 * delivery id and raw body are all inside the HMAC, so headers are
 * authenticated rather than informational. Receivers verify with
 * hash_equals + a timestamp tolerance; the documented algorithm ships
 * with a worked test vector in the module tests.
 */
final class WebhookSigner
{
    public static function sign(
        string $secret,
        string $timestamp,
        string $eventId,
        string $deliveryId,
        string $body,
    ): string {
        return hash_hmac(
            'sha256',
            implode('.', [$timestamp, $eventId, $deliveryId, $body]),
            $secret,
        );
    }
}
