<?php

namespace Modules\Content\Actions\Public\Page;

use Modules\Content\Models\Page;

abstract class AbstractPageAction
{
    public function __construct(protected Page $model) {}
}
