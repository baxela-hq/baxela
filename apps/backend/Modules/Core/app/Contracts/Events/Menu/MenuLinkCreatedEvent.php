<?php

namespace Modules\Core\Contracts\Events\Menu;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class MenuLinkCreatedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $menu_id;

    public ?int $parent_id;

    public string $url;

    public string $target;

    public ?int $position;

    public string $created_at;
}
