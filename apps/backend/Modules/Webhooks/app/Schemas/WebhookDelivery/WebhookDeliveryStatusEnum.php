<?php

namespace Modules\Webhooks\Schemas\WebhookDelivery;

enum WebhookDeliveryStatusEnum: string
{
    case PENDING = 'pending';

    case PROCESSING = 'processing';

    case SUCCEEDED = 'succeeded';

    case FAILED_RETRYABLE = 'failed_retryable';

    case FAILED_PERMANENT = 'failed_permanent';
}
