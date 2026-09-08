<?php

namespace Modules\Menu\Actions\Admin\MenuLink;

use Modules\Menu\Models\MenuLink;

abstract class AbstractMenuLinkAction
{
    public function __construct(protected MenuLink $model) {}
}
