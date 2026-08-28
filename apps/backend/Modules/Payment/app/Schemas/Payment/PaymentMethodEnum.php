<?php

namespace Modules\Payment\Schemas\Payment;

enum PaymentMethodEnum: string
{
    case PAYPAL = 'paypal';
    case STRIPE = 'stripe';
    case MANUAL = 'manual';
}
