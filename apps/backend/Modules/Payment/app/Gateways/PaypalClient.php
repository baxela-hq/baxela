<?php

namespace Modules\Payment\Gateways;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Payment\Exceptions\PaymentException;

/**
 * Thin SDK-less wrapper around PayPal's Orders v2 REST API (the official
 * PHP SDKs are deprecated; the API is plain REST). Keeps HTTP out of the
 * driver so tests only ever mock this class.
 */
class PaypalClient
{
    public function createOrder(array $params): object
    {
        return $this->request()->post('/v2/checkout/orders', $params)->throw()->object();
    }

    public function captureOrder(string $id): object
    {
        return $this->request()->post("/v2/checkout/orders/{$id}/capture", [])
            ->throw()->object();
    }

    /**
     * Certificate-based verification (unlike Stripe's HMAC): PayPal signs
     * the transmission headers, so the event travels with them and the
     * configured webhook id.
     *
     * @param  array<string, mixed>  $event  decoded webhook body
     * @param  array<string, string>  $transmission  auth_algo/cert_url/transmission_id/transmission_sig/transmission_time
     */
    public function verifyWebhookSignature(array $event, array $transmission): bool
    {
        $response = $this->request()->post('/v1/notifications/verify-webhook-signature', [
            ...$transmission,
            'webhook_id' => (string) config('payment.paypal.webhook_id'),
            'webhook_event' => $event,
        ])->throw();

        return $response->json('verification_status') === 'SUCCESS';
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->baseUrl($this->baseUrl())
            ->acceptJson();
    }

    private function accessToken(): string
    {
        $clientId = (string) config('payment.paypal.client_id');
        $clientSecret = (string) config('payment.paypal.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            // Fail as a safe 400 instead of an opaque auth error from PayPal.
            throw PaymentException::gatewayUnconfigured();
        }

        // PayPal tokens live ~9 hours; refresh slightly before expiry.
        return Cache::remember('payment.paypal.access_token', now()->addHours(8), function () use ($clientId, $clientSecret): string {
            return (string) Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
                ->throw()
                ->json('access_token');
        });
    }

    private function baseUrl(): string
    {
        return config('payment.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
