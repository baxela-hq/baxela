<?php

namespace Modules\Notification\Services\Notification\Enums;

enum TemplateEngineEnum: string
{
    case BLADE = 'blade';
    case DATABASE = 'database';
    case LOCALE = 'locale';
}
