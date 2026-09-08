<?php

namespace Modules\Core\Contracts\Events\Menu;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class MenuUpdatedEvent extends AbstractBaseEvent
{
    public int $id;

    public string $location;

    public bool $is_active;
}
