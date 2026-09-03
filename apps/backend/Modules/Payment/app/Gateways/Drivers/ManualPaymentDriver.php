<?php

namespace Modules\Payment\Gateways\Drivers;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Exceptions\PaymentException;

class ManualPaymentDriver implements PaymentDriverInterface
{
    public function initiate(PaymentInitiateInput $input): PaymentGatewayResult
    {
        // Offline flow: no hosted checkout to redirect to. The payment is
        // settled when an admin confirms it (UpdatePaymentAction).
        return new PaymentGatewayResult;
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        throw PaymentException::webhookNotSupported();
    }
}
