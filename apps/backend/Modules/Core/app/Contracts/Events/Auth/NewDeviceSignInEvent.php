<?php

namespace Modules\Core\Contracts\Events\Auth;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

/**
 * S.4: fired after commit when a successful sign-in used a device the
 * user has never been seen on before (auth_user_devices). The device
 * name is a client-reported label — the notification copy must not
 * claim verified identity (D6). Locale is captured at dispatch time
 * (Locale::fromRequest()) so the queued listener renders in the
 * recipient's language (D8, house pattern of OrderCreatedEvent).
 */
class NewDeviceSignInEvent extends AbstractBaseEvent
{
    public function __construct(
        public int $id,
        public string $email,
        public string $device_name,
        public string $signed_in_at,
        public ?string $locale = null,
    ) {}
}
