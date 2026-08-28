<?php

namespace Modules\User\Schemas\Address;

enum AddressTypeEnum: string
{
    case SHIPPING = 'shipping';
    case BILLING = 'billing';
}
