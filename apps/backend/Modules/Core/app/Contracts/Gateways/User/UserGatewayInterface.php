<?php

namespace Modules\Core\Contracts\Gateways\User;

interface UserGatewayInterface
{
    public function isUserAddressValid(string|int $userId, string|int $addressId): bool;
}
