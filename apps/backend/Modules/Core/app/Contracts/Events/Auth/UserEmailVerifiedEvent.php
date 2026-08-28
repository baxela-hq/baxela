<?php

namespace Modules\Core\Contracts\Events\Auth;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class UserEmailVerifiedEvent extends AbstractBaseEvent
{
    public function __construct(
        public int $id,
        public string $email,
        public string $email_verified_at,
    ) {}
}
