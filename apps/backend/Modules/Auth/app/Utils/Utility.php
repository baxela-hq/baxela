<?php

namespace Modules\Auth\Utils;

use Random\RandomException;

class Utility
{
    /**
     * @throws RandomException
     */
    public static function generateOtpCode(int $length = 6): string
    {
        return str_pad(random_int(0, 999999), $length, '0', STR_PAD_LEFT);
    }
}
