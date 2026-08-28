<?php

namespace Modules\Setting\Schemas\Setting;

enum SettingTypeEnum: string
{
    case STRING = 'string';
    case TEXT = 'text';
    case INTEGER = 'integer';
    case BOOLEAN = 'boolean';
    case JSON = 'json';
    case URL = 'url';
}
