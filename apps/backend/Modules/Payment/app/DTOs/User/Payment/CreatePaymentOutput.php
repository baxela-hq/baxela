<?php

namespace Modules\Payment\DTOs\User\Payment;

class CreatePaymentOutput
{
    public string $payment_id;

    public ?string $payment_url = null;
}
