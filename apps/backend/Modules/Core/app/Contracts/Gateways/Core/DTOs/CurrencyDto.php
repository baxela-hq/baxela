<?php

namespace Modules\Core\Contracts\Gateways\Core\DTOs;

class CurrencyDto
{
    public string $id;

    public string $code;

    public string $name;

    public string $native_name;

    public string $decimal_places;

    public string $symbol;

    public bool $is_default;

    public bool $is_symbol_right;

    public static function fill(array $input): self
    {
        $media = new self;
        foreach ($input as $key => $value) {
            if (property_exists($media, $key)) {
                $media->{$key} = $value;
            }
        }

        return $media;
    }
}
