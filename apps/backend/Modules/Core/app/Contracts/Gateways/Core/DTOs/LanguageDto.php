<?php

namespace Modules\Core\Contracts\Gateways\Core\DTOs;

class LanguageDto
{
    public string $id;

    public string $locale;

    public string $name;

    public string $native_name;

    public string $code2;

    public string $code3;

    public bool $is_rtl;

    public bool $is_active;

    public bool $is_default;

    public int $position;

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
