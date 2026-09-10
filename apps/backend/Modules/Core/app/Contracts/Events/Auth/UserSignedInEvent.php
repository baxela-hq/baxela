<?php

namespace Modules\Core\Contracts\Events\Auth;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class UserSignedInEvent extends AbstractBaseEvent
{
    public function __construct(
        public int $id,
        public string $email,
        public string $signed_in_at,
        /**
         * Guest cart token sent with the sign-in request, when present —
         * lets the Cart module fold the guest cart into the user's cart.
         */
        public ?string $cart_token = null,
    ) {}
}
