<?php

namespace Modules\Payment\Gateways;

use Modules\Payment\Exceptions\PaymentException;
use Stripe\Checkout\Session;
use Stripe\Refund;
use Stripe\StripeClient;
use Modules\Core\Contracts\Gateways\Payment\PaymentCredentialResolverInterface;

/**
 * Thin wrapper around the Stripe SDK's checkout session creation. Keeps the
 * fluent service chain out of the driver so tests only ever mock this class.
 */
class StripeCheckout
{

    public function __construct(
        private readonly PaymentCredentialResolverInterface $configs,
    ) {}
    public function createSession(array $params): Session
    {
        return $this->client()->checkout->sessions->create($params);
    }

    public function retrieveSession(string $id): Session
    {
        return $this->client()->checkout->sessions->retrieve($id);
    }

    /**
     * @param  array<string, mixed>  $params  e.g. ['payment_intent' => ..., 'amount' => ...]
     */
    public function createRefund(array $params, ?string $idempotencyKey = null): Refund
    {
        $options = is_null($idempotencyKey) ? [] : ['idempotency_key' => $idempotencyKey];

        return $this->client()->refunds->create($params, $options);
    }

    private function client(): StripeClient
    {
        $secret = (string) $this->configs->credential('stripe', 'secret');

        if ($secret === '') {
            // Fail as a safe 400 instead of the SDK's opaque 500.
            throw PaymentException::gatewayUnconfigured();
        }

        return new StripeClient($secret);
    }
}
