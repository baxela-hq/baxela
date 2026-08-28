<?php

namespace Modules\Core\DTOs\Trait;

trait ToArrayTrait
{
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
