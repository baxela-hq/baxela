<?php

namespace Modules\Payment\Gateways;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\Payment\Exceptions\PaymentException;

/**
 * Thin SDK-less wrapper around Razorpay's Payment Links API (plain REST,
 * one global base URL — the key pair decides test vs production). Keeps
 * HTTP out of the driver so tests only ever mock this class.
 */
class RazorpayClient
{
    public function createPaymentLink(array $params): object
    {
        return $this->request()->post('/v1/payment_links', $params)->throw()->object();
    }

    /**
     * Refund a captured payment. The ledger uuid rides in `notes` (echoed
     * back on the refund entity in webhook deliveries) and doubles as the
     * X-Refund-Idempotency key — Razorpay's refund-specific idempotency
     * header (≥10 chars, [A-Za-z0-9-_]) makes a retried call return the
     * original refund instead of creating a second one.
     *
     * @param  array<string, mixed>  $params
     */
    public function createRefund(string $paymentId, array $params, string $idempotencyKey): object
    {
        return $this->request()
            ->withHeaders(['X-Refund-Idempotency' => $idempotencyKey])
            ->post("/v1/payments/{$paymentId}/refund", $params)
            ->throw()->object();
    }

    /**
     * Webhook signature check, verifiable locally: x-razorpay-signature is
     * the hex HMAC-SHA256 of the RAW request body signed with the webhook
     * secret from the dashboard. Hashing a re-serialized payload instead
     * of the exact delivered bytes breaks verification, so the caller must
     * pass the raw content.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $expected = hash_hmac('sha256', $rawBody, (string) config('payment.razorpay.webhook_secret'));

        return hash_equals($expected, strtolower(trim($signature)));
    }

    private function request(): PendingRequest
    {
        $keyId = (string) config('payment.razorpay.key_id');
        $keySecret = (string) config('payment.razorpay.key_secret');

        if ($keyId === '' || $keySecret === '') {
            // Fail as a safe 400 instead of an opaque 401 from Razorpay.
            throw PaymentException::gatewayUnconfigured();
        }

        return Http::withBasicAuth($keyId, $keySecret)
            ->baseUrl('https://api.razorpay.com')
            ->acceptJson();
    }
}
