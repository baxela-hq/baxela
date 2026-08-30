<?php

namespace Modules\Shipping\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case METHOD_CREATION_FAILED = 'shipping.method.creation_failed';

    case METHOD_UPDATE_FAILED = 'shipping.method.update_failed';

    case METHOD_INVALID_ADDRESS = 'shipping.method.invalid_address';

    case ZONE_CREATION_FAILED = 'shipping.zone.creation_failed';

    case ZONE_UPDATE_FAILED = 'shipping.zone.update_failed';

    case RATE_CREATION_FAILED = 'shipping.rate.creation_failed';

    case RATE_UPDATE_FAILED = 'shipping.rate.update_failed';

    case SHIPMENT_CREATION_FAILED = 'shipping.shipment.creation_failed';

    case SHIPMENT_UPDATE_FAILED = 'shipping.shipment.update_failed';

    case SHIPMENT_ORDER_NOT_FOUND = 'shipping.shipment.order_not_found';

    case SHIPMENT_ALREADY_EXISTS = 'shipping.shipment.already_exists';

    case SHIPMENT_NOT_FOUND = 'shipping.shipment.not_found';

    case SHIPMENT_INVALID_TRANSITION = 'shipping.shipment.invalid_transition';
}
