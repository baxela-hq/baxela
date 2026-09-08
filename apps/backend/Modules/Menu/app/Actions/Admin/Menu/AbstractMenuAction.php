<?php

namespace Modules\Menu\Actions\Admin\Menu;

use Modules\Menu\Models\Menu;

abstract class AbstractMenuAction
{
    public function __construct(protected Menu $model) {}
}
