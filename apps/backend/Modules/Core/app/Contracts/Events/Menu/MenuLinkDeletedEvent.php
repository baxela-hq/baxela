<?php

namespace Modules\Core\Contracts\Events\Menu;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class MenuLinkDeletedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $menu_id;
}
