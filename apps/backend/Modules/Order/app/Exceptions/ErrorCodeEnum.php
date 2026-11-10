<?php

namespace Modules\Order\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case UPDATE_INVALID_STATUS_TRANSITION = 'order.update.invalid_status_transition';

    case UPDATE_INVALID_PAYMENT_STATUS_TRANSITION = 'order.update.invalid_payment_status_transition';

    case ORDER_NOT_FOUND = 'order.not_found';

    case RETURN_NOT_FOUND = 'order.return.not_found';

    case RETURN_INELIGIBLE = 'order.return.ineligible';

    case RETURN_INVALID_TRANSITION = 'order.return.invalid_transition';

    case INVOICE_NOT_FOUND = 'order.invoice.not_found';

    case INVOICE_INELIGIBLE = 'order.invoice.ineligible';

    case INVOICE_INVALID_TRANSITION = 'order.invoice.invalid_transition';

    case INVOICE_PDF_NOT_READY = 'order.invoice.pdf_not_ready';

    case INVOICE_PDF_NOT_RETRYABLE = 'order.invoice.pdf_not_retryable';

    case CREDIT_NOTE_EXCEEDS_BALANCE = 'order.credit_note.exceeds_balance';
}
