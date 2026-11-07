<?php

namespace Modules\Order\Schemas\OrderInvoice;

enum OrderInvoiceStatusEnum: string
{
    case ACTIVE = 'active';

    case VOID = 'void';
}
