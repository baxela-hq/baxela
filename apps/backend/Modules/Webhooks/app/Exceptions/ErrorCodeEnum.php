<?php

namespace Modules\Webhooks\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case ENDPOINT_NOT_FOUND = 'webhooks.endpoint.not_found';

    case DELIVERY_NOT_FOUND = 'webhooks.delivery.not_found';

    case URL_INVALID = 'webhooks.url.invalid';

    case EVENTS_INVALID = 'webhooks.events.invalid';

    case DELIVERY_NOT_RETRYABLE = 'webhooks.delivery.not_retryable';
}
