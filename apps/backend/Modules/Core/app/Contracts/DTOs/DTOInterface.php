<?php

namespace Modules\Core\Contracts\DTOs;

use Illuminate\Foundation\Http\FormRequest;

interface DTOInterface
{
    public static function fromArray(array $data): static;

    public static function fromRequest(FormRequest $request): static;

    public function toArray(): array;
}
