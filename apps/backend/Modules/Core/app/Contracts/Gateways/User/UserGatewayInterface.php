<?php

namespace Modules\Core\Contracts\Gateways\User;

use Modules\Core\Contracts\Gateways\User\DTOs\AddressDto;

interface UserGatewayInterface
{
    public function isUserAddressValid(string|int $userId, string|int $addressId): bool;

    public function getAddress(string|int $userId, string|int $addressId): ?AddressDto;
}
