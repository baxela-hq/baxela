<?php

namespace Modules\Core\Contracts\Gateways\Payment;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;

interface PaymentDriverInterface
{
    /**
     * Start a gateway transaction for a freshly created PENDING payment.
     * Drivers without a hosted checkout (e.g. manual) return nulls.
     */
    public function initiate(PaymentInitiateInput $input): PaymentGatewayResult;

    /**
     * Verify a gateway webhook call and map it to a stored payment via
     * its transaction id. `status` uses PaymentStatusEnum values.
     */
    public function handleWebhook(Request $request): WebhookResult;
}
