<?php

namespace Modules\Payment\Gateways;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\Payment\Exceptions\PaymentException;

/**
 * Thin SDK-less wrapper around Adyen's Checkout API (Pay by Link). Keeps
 * HTTP out of the driver so tests only ever mock this class.
 */
class AdyenCheckout
{
    public function createPaymentLink(array $params): object
    {
        return $this->request()->post('/paymentLinks', $params)->throw()->object();
    }

    /**
     * Standard-webhook HMAC check, verifiable locally (unlike PayPal's
     * certificate check): Adyen signs the canonical string
     * pspReference:originalReference:merchantAccountCode:merchantReference:
     * success — missing segments as empty strings, `\` and `:` escaped
     * inside values — with the base64-decoded Customer Area key.
     *
     * @param  array<string, mixed>  $item  decoded NotificationRequestItem
     */
    public function verifyWebhookSignature(array $item): bool
    {
        $expected = base64_encode(hash_hmac(
            'sha256',
            $this->canonicalString($item),
            base64_decode((string) config('payment.adyen.hmac_key')),
            true,
        ));

        return hash_equals($expected, (string) ($item['additionalData']['hmacSignature'] ?? ''));
    }

    private function request(): PendingRequest
    {
        $apiKey = (string) config('payment.adyen.api_key');

        if ($apiKey === '') {
            // Fail as a safe 400 instead of an opaque 401 from Adyen.
            throw PaymentException::gatewayUnconfigured();
        }

        return Http::withHeaders(['x-api-key' => $apiKey])
            ->baseUrl($this->baseUrl())
            ->acceptJson();
    }

    private function baseUrl(): string
    {
        // Regional live hosts (e.g. checkout-live-us.adyen.com) exist but
        // the generic one serves every region.
        return config('payment.adyen.env') === 'live'
            ? 'https://checkout-live.adyen.com/v69'
            : 'https://checkout-test.adyen.com/v69';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function canonicalString(array $item): string
    {
        $success = ($item['success'] ?? false) === true || ($item['success'] ?? false) === 'true';

        $segments = [
            (string) ($item['pspReference'] ?? ''),
            (string) ($item['originalReference'] ?? ''),
            (string) ($item['merchantAccountCode'] ?? ''),
            (string) ($item['merchantReference'] ?? ''),
            $success ? 'true' : 'false',
        ];

        return implode(':', array_map(
            fn (string $segment): string => str_replace(['\\', ':'], ['\\\\', '\\:'], $segment),
            $segments,
        ));
    }
}
