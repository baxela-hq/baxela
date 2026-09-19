<?php

namespace Modules\Payment\Gateways;

use Stripe\Checkout\Session;
use Stripe\StripeClient;

/**
 * Thin wrapper around the Stripe SDK's checkout session creation. Keeps the
 * fluent service chain out of the driver so tests only ever mock this class.
 */
class StripeCheckout
{
    public function createSession(array $params): Session
    {
        return $this->client()->checkout->sessions->create($params);
    }

    private function client(): StripeClient
    {
        return new StripeClient((string) config('payment.stripe.secret'));
    }
}
