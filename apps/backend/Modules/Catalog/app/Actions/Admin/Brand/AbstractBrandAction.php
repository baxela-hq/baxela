<?php

namespace Modules\Catalog\Actions\Admin\Brand;

use Modules\Catalog\Models\Brand;

class AbstractBrandAction
{
    public function __construct(protected Brand $model) {}
}
