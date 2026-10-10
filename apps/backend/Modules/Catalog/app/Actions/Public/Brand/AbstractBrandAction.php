<?php

namespace Modules\Catalog\Actions\Public\Brand;

use Modules\Catalog\Models\Brand;

class AbstractBrandAction
{
    public function __construct(protected Brand $model) {}
}
