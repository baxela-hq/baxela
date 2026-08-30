<?php

namespace Modules\Core\Contracts\Gateways\Shipping\DTOs;

class ShippingMethodQuoteDto
{
    public int $id;

    public string $name;

    public float $price;

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
