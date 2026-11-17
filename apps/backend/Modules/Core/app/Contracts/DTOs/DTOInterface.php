<?php

namespace Modules\Core\Contracts\DTOs;

use Illuminate\Foundation\Http\FormRequest;

interface DTOInterface
{
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static;

    public static function fromRequest(FormRequest $request): static;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
