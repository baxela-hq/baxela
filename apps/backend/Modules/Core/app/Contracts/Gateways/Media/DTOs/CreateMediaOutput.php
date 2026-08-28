<?php

namespace Modules\Core\Contracts\Gateways\Media\DTOs;

class CreateMediaOutput
{
    public string $id;

    public string $module_name;

    public ?string $entity_type = null;

    public ?string $entity_id = null;

    public string $url;

    public ?string $collection = null;

    public string $filename;

    public int $size;

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
