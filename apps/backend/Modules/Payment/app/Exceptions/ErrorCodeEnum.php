<?php

namespace Modules\Payment\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case PROCESS_INVALID_ORDER = 'payment.process.invalid_order';

    case PROCESS_METHOD_NOT_SUPPORTED = 'payment.process.method_not_supported';

    case PROCESS_METHOD_INACTIVE = 'payment.process.method_inactive';

    case PROCESS_GATEWAY_UNCONFIGURED = 'payment.process.gateway_unconfigured';

    case UPDATE_INVALID_STATUS_TRANSITION = 'payment.update.invalid_status_transition';

    case WEBHOOK_NOT_SUPPORTED = 'payment.webhook.not_supported';

    case WEBHOOK_INVALID = 'payment.webhook.invalid';

    case REFUND_NOT_SUPPORTED = 'payment.refund.not_supported';

    case REFUND_EXCEEDS_REFUNDABLE = 'payment.refund.exceeds_refundable';

    case REFUND_INVALID_STATUS = 'payment.refund.invalid_status';

    case REFUND_FAILED = 'payment.refund.failed';

    case REFUND_OUTCOME_UNKNOWN = 'payment.refund.outcome_unknown';
}
