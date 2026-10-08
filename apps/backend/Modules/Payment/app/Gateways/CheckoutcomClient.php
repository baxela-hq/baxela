<?php

namespace Modules\Payment\Gateways;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\Payment\Exceptions\PaymentException;

/**
 * Thin SDK-less wrapper around Checkout.com's Payment Links API (plain
 * REST). Keeps HTTP out of the driver so tests only ever mock this class.
 */
class CheckoutcomClient
{
    public function createPaymentLink(array $params): object
    {
        return $this->request()->post('/payment-links', $params)->throw()->object();
    }

    /**
     * Webhook signature check, verifiable locally: cko-signature is the
     * HMAC-SHA256 of the RAW request body signed with the webhook signing
     * secret — a separate secret from the API key. Hashing re-serialized
     * JSON instead of the exact delivered bytes is Checkout.com's most
     * documented verification failure, so the caller must pass the raw
     * content.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = (string) config('payment.checkoutcom.webhook_secret');
        $signature = strtolower(trim($signature));

        // The signature may arrive hex or base64 encoded depending on the
        // notification version; both are valid MACs of the same body with
        // the same secret.
        $candidates = [
            hash_hmac('sha256', $rawBody, $secret),
            strtolower(base64_encode(hash_hmac('sha256', $rawBody, $secret, true))),
        ];

        return collect($candidates)->contains(fn (string $candidate): bool => hash_equals($candidate, $signature));
    }

    private function request(): PendingRequest
    {
        $secretKey = (string) config('payment.checkoutcom.secret_key');

        if ($secretKey === '') {
            // Fail as a safe 400 instead of an opaque 401 from Checkout.com.
            throw PaymentException::gatewayUnconfigured();
        }

        // Checkout.com wants the raw key, without a Bearer prefix.
        return Http::withHeaders(['Authorization' => $secretKey])
            ->baseUrl($this->baseUrl())
            ->acceptJson();
    }

    private function baseUrl(): string
    {
        return config('payment.checkoutcom.env') === 'live'
            ? 'https://api.checkout.com'
            : 'https://api.sandbox.checkout.com';
    }
}
