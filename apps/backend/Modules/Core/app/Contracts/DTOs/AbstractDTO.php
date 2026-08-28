<?php

namespace Modules\Core\Contracts\DTOs;

use Illuminate\Foundation\Http\FormRequest;

abstract class AbstractDTO
{
    abstract public static function fromArray(array $data): static;

    public static function fromRequest(FormRequest $request): static
    {
        return self::fromArray($request->validate());
    }
}
