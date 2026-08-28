<?php

namespace Modules\Core\DTOs\Trait;

trait FillTrait
{
    public static function fill(array $input): self
    {
        $media = new static;
        foreach ($input as $key => $value) {
            if (property_exists($media, $key)) {
                $media->{$key} = $value instanceof \BackedEnum ? $value->value : $value;
            }
        }

        return $media;
    }
}
