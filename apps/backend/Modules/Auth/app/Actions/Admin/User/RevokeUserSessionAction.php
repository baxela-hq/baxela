<?php

namespace Modules\Auth\Actions\Admin\User;

use Modules\Auth\Models\User;
use Modules\Core\Contracts\Events\Auth\UserSessionRevokedEvent;
use Modules\Core\Utils\Auth;

class RevokeUserSessionAction extends AbstractUserAction
{
    /**
     * Force-revoke ONE of the target user's sessions. The query is scoped
     * to the target user's tokens, so foreign session ids 404 instead of
     * leaking their existence.
     */
    public function handle(string $id, string $tokenId): bool
    {
        $user = $this->model->query()->findOrFail($id);

        $session = $user->tokens()->whereKey($tokenId)->firstOrFail();

        $deleted = (bool) $session->delete();

        event(new UserSessionRevokedEvent(
            id: $user->id,
            email: $user->email,
            session_id: (int) $tokenId,
            revoked_by: Auth::id(),
        ));

        return $deleted;
    }
}
