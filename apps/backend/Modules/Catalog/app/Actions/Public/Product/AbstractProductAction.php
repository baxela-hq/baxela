<?php

namespace Modules\Catalog\Actions\Public\Product;

use Modules\Catalog\Models\Product;

abstract class AbstractProductAction
{
    public function __construct(protected Product $model) {}
}
