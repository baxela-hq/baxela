<?php

namespace Modules\Catalog\Schemas\Attribute;

use Modules\Core\Schemas\Shared\ToArrayTrait;

enum AttributeTypeEnum: string
{
    use ToArrayTrait;

    case TEXT = 'text';
    case NUMBER = 'number';
    case BOOLEAN = 'boolean';
    case SELECT = 'select';
    case MULTISELECT = 'multiselect';
}
