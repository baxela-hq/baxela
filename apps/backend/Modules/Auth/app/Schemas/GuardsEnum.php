<?php

namespace Modules\Auth\Schemas;

enum GuardsEnum: string
{
    case USER_SESSION = 'user_session';
    case USER = 'user';
}
