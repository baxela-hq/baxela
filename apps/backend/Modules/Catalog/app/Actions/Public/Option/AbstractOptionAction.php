<?php

namespace Modules\Catalog\Actions\Public\Option;

use Modules\Catalog\Models\Option;

class AbstractOptionAction
{
    public function __construct(protected Option $model) {}
}
