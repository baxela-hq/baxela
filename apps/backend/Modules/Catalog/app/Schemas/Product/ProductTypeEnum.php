<?php

namespace Modules\Catalog\Schemas\Product;

enum ProductTypeEnum: string
{
    case SIMPLE = 'simple';
    case VARIABLE = 'variable';
}
