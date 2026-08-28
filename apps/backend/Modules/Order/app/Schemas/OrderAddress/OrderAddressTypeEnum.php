<?php

namespace Modules\Order\Schemas\OrderAddress;

enum OrderAddressTypeEnum: string
{
    case SHIPPING = 'shipping';
    case BILLING = 'billing';
}
