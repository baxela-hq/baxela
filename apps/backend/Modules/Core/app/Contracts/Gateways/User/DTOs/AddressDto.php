<?php

namespace Modules\Core\Contracts\Gateways\User\DTOs;

class AddressDto
{
    public int $id;

    public int $user_id;

    public string $type;

    public string $full_name;

    public string $phone;

    public string $address_line;

    public string $city;

    public ?string $postal_code = null;

    public ?string $country_code = null;

    public bool $is_default;

    public static function fill(array $input): self
    {
        $dto = new self;
        foreach ($input as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->{$key} = $value;
            }
        }

        return $dto;
    }
}
