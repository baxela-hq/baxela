<?php

namespace Modules\Core\Contracts\Events\Auth;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class UserSignedInEvent extends AbstractBaseEvent
{
    public function __construct(
        public int $id,
        public string $email,
        public string $signed_in_at,
    ) {}
}
