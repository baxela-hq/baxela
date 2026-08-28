<?php

namespace Modules\Core\Contracts\Gateways\Media\DTOs;

enum ModuleNameEnum: string
{
    case CATALOG = 'catalog';
    case CONTENT = 'content';
    case SETTING = 'setting';
    case USER = 'user';
    case GLOBAL = 'global';
}
