<?php

namespace Modules\Core\Schemas\Shared;

trait ToArrayTrait
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
