<?php

namespace Modules\Core\Contracts\Events\Menu;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class MenuCreatedEvent extends AbstractBaseEvent
{
    public int $id;

    public string $location;

    public bool $is_active;

    public string $created_at;
}
