<?php

namespace Modules\Catalog\Schemas\Product;

enum WeightUnitEnum: string
{
    case KILOGRAM = 'kg';

    case GRAM = 'g';

    case POUND = 'lb';

    case OUNCE = 'oz';
}
