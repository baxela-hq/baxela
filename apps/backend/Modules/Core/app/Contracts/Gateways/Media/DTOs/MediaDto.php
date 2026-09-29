<?php

namespace Modules\Core\Contracts\Gateways\Media\DTOs;

class MediaDto
{
    public int $id;

    public string $disk;

    public string $path;

    public string $name;

    public ?string $extension = null;

    public ?string $mime_type = null;

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
