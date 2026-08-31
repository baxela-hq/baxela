<?php

namespace Modules\Payment\Actions\User\Payment;

use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Utils\Auth;
use Modules\Payment\DTOs\User\Payment\CreatePaymentOutput;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Http\Requests\User\Payment\PaymentRequest;
use Modules\Payment\Models\Payment;
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

        $payment = Payment::create([
            PaymentSchema::ORDER_ID => $request->input(PaymentSchema::ORDER_ID),
            PaymentSchema::METHOD => $request->input(PaymentSchema::METHOD),
            PaymentSchema::STATUS => PaymentStatusEnum::PENDING,
            PaymentSchema::AMOUNT => $order->total_amount,
        ]);

        $paymentDto = new CreatePaymentOutput;
        $paymentDto->payment_url = config('app.url');

        return $paymentDto;
    }
}
