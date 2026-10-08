<?php

namespace Modules\Payment\Gateways;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\Payment\Exceptions\PaymentException;

/**
 * Thin SDK-less wrapper around NowPayments' invoice API (plain REST). Keeps
 * HTTP out of the driver so tests only ever mock this class.
 */
class NowpaymentsClient
{
    public function createInvoice(array $params): object
    {
        return $this->request()->post('/v1/invoice', $params)->throw()->object();
    }

    /**
     * IPN signature check, verifiable locally: x-nowpayments-sig is the
     * hex HMAC-SHA512 of the IPN body re-serialized with its object keys
     * sorted alphabetically (recursively). The re-encoding must not escape
     * slashes or unicode — NowPayments' own serializer does not.
     *
     * @param  array<string, mixed>  $ipn  decoded IPN body
     */
    public function verifyWebhookSignature(array $ipn, string $signature): bool
    {
        $expected = hash_hmac(
            'sha512',
            json_encode($this->sortKeys($ipn), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            (string) config('payment.nowpayments.ipn_secret'),
        );

        return hash_equals($expected, strtolower(trim($signature)));
    }

    private function request(): PendingRequest
    {
        $apiKey = (string) config('payment.nowpayments.api_key');

        if ($apiKey === '') {
            // Fail as a safe 400 instead of an opaque 401 from NowPayments.
            throw PaymentException::gatewayUnconfigured();
        }

        return Http::withHeaders(['x-api-key' => $apiKey])
            ->baseUrl($this->baseUrl())
            ->acceptJson();
    }

    private function baseUrl(): string
    {
        return config('payment.nowpayments.env') === 'live'
            ? 'https://api.nowpayments.io'
            : 'https://api-sandbox.nowpayments.io';
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function sortKeys(array $value): array
    {
        ksort($value);

        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->sortKeys($item);
            }
        }

        return $value;
    }
}
