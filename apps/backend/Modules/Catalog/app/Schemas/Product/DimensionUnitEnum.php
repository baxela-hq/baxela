<?php

namespace Modules\Catalog\Schemas\Product;

enum DimensionUnitEnum: string
{
    case MILLIMETER = 'mm';

    case CENTIMETER = 'cm';

    case METER = 'm';

    case INCH = 'in';
}
