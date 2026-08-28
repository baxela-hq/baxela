<?php

namespace Modules\Payment\Schemas\Payment;

enum PaymentStatusEnum: string
{
    case PENDING = 'pending';
    case SUCCESS = 'success';
    case FAILED = 'failed';
}
