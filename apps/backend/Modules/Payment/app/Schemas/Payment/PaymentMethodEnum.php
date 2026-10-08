<?php

namespace Modules\Payment\Schemas\Payment;

enum PaymentMethodEnum: string
{
    case PAYPAL = 'paypal';
    case STRIPE = 'stripe';
    case ADYEN = 'adyen';
    case NOWPAYMENTS = 'nowpayments';
    case MERCADOPAGO = 'mercadopago';
    case MANUAL = 'manual';
}
