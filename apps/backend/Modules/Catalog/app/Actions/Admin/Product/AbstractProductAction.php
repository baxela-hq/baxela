<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Modules\Catalog\Models\Product;

abstract class AbstractProductAction
{
    public function __construct(protected Product $model) {}
}
