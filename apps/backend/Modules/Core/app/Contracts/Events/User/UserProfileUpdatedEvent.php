<?php

namespace Modules\Core\Contracts\Events\User;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class UserProfileUpdatedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $user_id;

    public string $full_name;

    public ?string $display_name;

    public ?string $bio;

    public ?string $avatar;

    public ?string $gender;

    public ?string $date_of_birth;
}
