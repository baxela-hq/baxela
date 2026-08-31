<?php

namespace Modules\User\Gateways;

use Modules\Core\Contracts\Gateways\User\DTOs\AddressDto;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;
use Modules\User\Models\Address;
use Modules\User\Models\Profile;
use Modules\User\Schemas\Address\AddressSchema;
use Modules\User\Schemas\Profile\ProfileSchema;

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

    public function getUserNamesByIds(array $ids): array
    {
        return Profile::query()
            ->where(ProfileSchema::USER_ID, $ids)
            ->get()
            ->mapWithKeys(function (Profile $profile) {
                $name = trim(implode(' ', array_filter([
                    $profile->{ProfileSchema::FIRST_NAME},
                    $profile->{ProfileSchema::LAST_NAME},
                ])));

                return [$profile->{ProfileSchema::USER_ID} => $name !== '' ? $name : null];
            })
            ->all();
    }
}
