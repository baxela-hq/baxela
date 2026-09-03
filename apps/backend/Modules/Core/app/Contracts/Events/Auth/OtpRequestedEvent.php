<?php

namespace Modules\Core\Contracts\Events\Auth;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class OtpRequestedEvent extends AbstractBaseEvent
{
    public string $email;

    public string $code;

    public string $action;
}
