<?php

namespace Modules\Core\Contracts\Events;

use Modules\Core\DTOs\Trait\FillTrait;
use Modules\Core\DTOs\Trait\ToArrayTrait;

abstract class AbstractBaseEvent
{
    use FillTrait;
    use ToArrayTrait;

    public static function getClassName(): string
    {
        return class_basename(static::class);
    }
}
