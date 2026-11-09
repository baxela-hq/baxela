<?php

namespace Modules\Payment\Gateways\Drivers;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\ConfigurableDriver;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\RefundableDriver;
use Modules\Payment\Exceptions\PaymentException;

class ManualPaymentDriver implements ConfigurableDriver, RefundableDriver
{
    public function credentialKeys(): array
    {
        // Offline settlement needs no gateway credentials.
        return [];
    }

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

    public function refund(PaymentRefundInput $input): PaymentRefundResult
    {
        // Offline settlement mirrors offline refund: the admin executing
        // the ledger refund confirms the money has already been returned
        // outside Baxela (the UI states this explicitly). No gateway id.
        return new PaymentRefundResult;
    }
}
