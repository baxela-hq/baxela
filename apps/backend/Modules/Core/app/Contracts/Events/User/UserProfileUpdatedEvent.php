<?php

namespace Modules\Core\Contracts\Events\User;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class UserProfileUpdatedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $user_id;

    public string $first_name;

    public string $last_name;
}
