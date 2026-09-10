<?php

namespace Modules\Core\Contracts\Events\Cart;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class CartCreatedEvent extends AbstractBaseEvent
{
    public ?int $id = null;

    public ?int $user_id = null;

    public ?string $updated_at = null;
}
