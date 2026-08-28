<?php

namespace Modules\Core\Contracts\Events\Auth;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class UserSignedUpEvent extends AbstractBaseEvent
{
    public function __construct(
        public int $id,
        public string $email,
        public string $signed_up_at,
    ) {}
}
