<?php

namespace Modules\Payment\Gateways;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\Payment\Exceptions\PaymentException;

/**
 * Thin SDK-less wrapper around Mercado Pago's Checkout Pro preferences
 * and payments APIs (plain REST, one global base URL — the access token
 * decides test vs production). Keeps HTTP out of the driver so tests
 * only ever mock this class.
 */
class MercadopagoClient
{
    public function createPreference(array $params): object
    {
        return $this->request()->post('/checkout/preferences', $params)->throw()->object();
    }

    public function getPayment(string $id): object
    {
        return $this->request()->get("/v1/payments/{$id}")->throw()->object();
    }

    /**
     * Webhook signature check, verifiable locally: x-signature carries
     * ts=…,v1=…, where v1 is the hex HMAC-SHA256 of the manifest
     * id:{data.id};request-id:{x-request-id};ts:{ts}; signed with the
     * dashboard-configured webhook secret — not the access token.
     */
    public function verifyWebhookSignature(string $dataId, string $requestId, string $signature): bool
    {
        $ts = [];
        $v1 = [];

        if (! preg_match('/ts=([^,]+)/', $signature, $ts) || ! preg_match('/v1=([^,\s]+)/', $signature, $v1)) {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts[1]};";

        $expected = hash_hmac('sha256', $manifest, (string) config('payment.mercadopago.webhook_secret'));

        return hash_equals($expected, strtolower(trim($v1[1])));
    }

    private function request(): PendingRequest
    {
        $token = (string) config('payment.mercadopago.access_token');

        if ($token === '') {
            // Fail as a safe 400 instead of an opaque 401 from Mercado Pago.
            throw PaymentException::gatewayUnconfigured();
        }

        return Http::withToken($token)
            ->baseUrl('https://api.mercadopago.com')
            ->acceptJson();
    }
}
