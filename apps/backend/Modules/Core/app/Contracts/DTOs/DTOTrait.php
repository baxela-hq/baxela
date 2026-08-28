<?php

namespace Modules\Core\Contracts\DTOs;

use Illuminate\Foundation\Http\FormRequest;

trait DTOTrait
{
    public static function fromArray(array $data): static
    {
        $class_vars = get_class_vars(get_class((object) static::class));

        $self = new static;
        foreach ($class_vars as $name => $value) {
            $self->{$name} = $value;
        }

        return $self;
    }

    public static function fromRequest(FormRequest $request): static
    {
        return self::fromArray($request->validated());
    }

    public function toArray(): array
    {
        return get_class_vars(get_class((object) static::class));
    }
}
