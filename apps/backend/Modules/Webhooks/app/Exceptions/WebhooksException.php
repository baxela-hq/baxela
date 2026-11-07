<?php

namespace Modules\Webhooks\Exceptions;

use Modules\Core\Exceptions\BaseException;

class WebhooksException extends BaseException
{
    public static function endpointNotFound(): WebhooksException
    {
        return new self(ErrorCodeEnum::ENDPOINT_NOT_FOUND->value, 404);
    }

    public static function deliveryNotFound(): WebhooksException
    {
        return new self(ErrorCodeEnum::DELIVERY_NOT_FOUND->value, 404);
    }

    /**
     * SSRF-rejecting URL (private host, wrong scheme, bad port, ...).
     * The message never echoes the submitted URL beyond the failure
     * category.
     */
    public static function urlInvalid(): WebhooksException
    {
        return new self(ErrorCodeEnum::URL_INVALID->value);
    }

    public static function eventsInvalid(): WebhooksException
    {
        return new self(ErrorCodeEnum::EVENTS_INVALID->value, 422);
    }

    public static function deliveryNotRetryable(): WebhooksException
    {
        return new self(ErrorCodeEnum::DELIVERY_NOT_RETRYABLE->value);
    }
}
