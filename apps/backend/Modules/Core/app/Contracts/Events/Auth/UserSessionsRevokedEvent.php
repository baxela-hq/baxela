<?php

namespace Modules\Core\Contracts\Events\Auth;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

/**
 * A super-admin force-revoked ALL of a user's sessions (S.2). The audit
 * listener derives module/entity/action from this class name.
 */
class UserSessionsRevokedEvent extends AbstractBaseEvent
{
    public function __construct(
        public int $id,
        public string $email,
        public int $revoked_count,
        public ?int $revoked_by = null,
    ) {}
}
