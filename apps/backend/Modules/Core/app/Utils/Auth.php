<?php

namespace Modules\Core\Utils;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth as FacadesAuth;

class Auth
{
    public static function user(): ?Authenticatable
    {
        return FacadesAuth::user();
    }

    public static function id(): int|string|null
    {
        return FacadesAuth::id();
    }

    public static function check(): bool
    {
        return FacadesAuth::check();
    }
}
