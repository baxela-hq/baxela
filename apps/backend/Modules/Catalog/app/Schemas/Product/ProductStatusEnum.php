<?php

namespace Modules\Catalog\Schemas\Product;

enum ProductStatusEnum: string
{
    case DISCONTINUED = 'discontinued';
    case OUT_OF_STOCK = 'out_of_stock';
    case IN_STOCK = 'in_stock';
    case INACTIVE = 'inactive';
}
