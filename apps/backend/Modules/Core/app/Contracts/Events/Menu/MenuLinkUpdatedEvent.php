<?php

namespace Modules\Core\Contracts\Events\Menu;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class MenuLinkUpdatedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $menu_id;

    public ?int $parent_id;

    public ?int $position;
}
