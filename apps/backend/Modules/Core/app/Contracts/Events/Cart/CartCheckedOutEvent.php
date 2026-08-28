<?php

namespace Modules\Core\Contracts\Events\Cart;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class CartCheckedOutEvent extends AbstractBaseEvent
{
    public int $id;

    public int $user_id;

    public string $updated_at;
}
