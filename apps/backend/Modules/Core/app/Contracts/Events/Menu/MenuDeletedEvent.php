<?php

namespace Modules\Core\Contracts\Events\Menu;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class MenuDeletedEvent extends AbstractBaseEvent
{
    public int $id;
}
