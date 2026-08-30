<?php

namespace Modules\User\Gateways;

use Modules\Core\Contracts\Gateways\User\DTOs\AddressDto;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;
use Modules\User\Models\Address;
use Modules\User\Schemas\Address\AddressSchema;

class UserGateway implements UserGatewayInterface
{
    public function isUserAddressValid(string|int $userId, string|int $addressId): bool
    {
        return Address::query()->where([
            AddressSchema::ID => $addressId,
            AddressSchema::USER_ID => $userId,
        ])->exists();
    }

    public function getAddress(string|int $userId, string|int $addressId): ?AddressDto
    {
        $record = Address::query()->where([
            AddressSchema::ID => $addressId,
            AddressSchema::USER_ID => $userId,
        ])->first();

        return $record ? AddressDto::fill($record->toArray()) : null;
    }
}
