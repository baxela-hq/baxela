<?php

namespace Modules\Payment\Actions\User\Payment;

use Modules\Core\Contracts\Events\Payment\PaymentInitiatedEvent;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Utils\Auth;
use Modules\Payment\DTOs\User\Payment\CreatePaymentOutput;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Http\Requests\User\Payment\PaymentRequest;
use Modules\Payment\Models\Payment;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;

class CreatePaymentAction
{
    public function __construct(protected Payment $model) {}

    /**
     * @throws PaymentException
     */
    public function handle(PaymentRequest $request): CreatePaymentOutput
    {
        $orderGateway = app(OrderGatewayInterface::class);
        $order = $orderGateway->getOrder($request->input(PaymentSchema::ORDER_ID), Auth::id());
        if (! $order) {
            throw PaymentException::processInvalidOrder();
        }

        $method = PaymentMethodEnum::from($request->input(PaymentSchema::METHOD));
        $driver = app(PaymentDriverManager::class)->forMethod($method);

        $orderId = (int) $request->input(PaymentSchema::ORDER_ID);

        $payment = Payment::create([
            PaymentSchema::ORDER_ID => $orderId,
            PaymentSchema::METHOD => $method,
            PaymentSchema::STATUS => PaymentStatusEnum::PENDING,
            PaymentSchema::AMOUNT => $order->total_amount,
        ]);

        // Initiated after the row is committed: a gateway outage must not
        // roll the payment back mid-transaction — it stays PENDING and the
        // order expiry handles the rest.
        $result = $driver->initiate(new PaymentInitiateInput(
            payment_id: $payment->{PaymentSchema::ID},
            order_id: $orderId,
            amount: $order->total_amount,
            method: $method->value,
        ));

        if (! is_null($result->transaction_id)) {
            $payment->{PaymentSchema::TRANSACTION_ID} = $result->transaction_id;
            $payment->save();
        }

        event(PaymentInitiatedEvent::fill([
            PaymentSchema::ID => $payment->{PaymentSchema::ID},
            PaymentSchema::ORDER_ID => $orderId,
            PaymentSchema::AMOUNT => $payment->{PaymentSchema::AMOUNT},
            PaymentSchema::METHOD => $method->value,
            PaymentSchema::STATUS => PaymentStatusEnum::PENDING->value,
        ]));

        $paymentDto = new CreatePaymentOutput;
        $paymentDto->payment_id = (string) $payment->{PaymentSchema::ID};
        $paymentDto->payment_url = $result->redirect_url;

        return $paymentDto;
    }
}
