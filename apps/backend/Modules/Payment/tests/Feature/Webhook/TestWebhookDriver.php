<?php

namespace Modules\Payment\Tests\Feature\Webhook;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Exceptions\PaymentException;

/**
 * Minimal gateway stand-in: trusts a shared-secret header as its signature
 * check and maps the payload to a WebhookResult.
 */
class TestWebhookDriver implements PaymentDriverInterface
{
    public function initiate(PaymentInitiateInput $input): PaymentGatewayResult
    {
        return new PaymentGatewayResult;
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        if ($request->header('X-Test-Signature') !== 'secret') {
            throw PaymentException::webhookInvalid();
        }

        return new WebhookResult(
            transaction_id: (string) $request->input('transaction_id'),
            status: (string) $request->input('status'),
        );
    }
}
