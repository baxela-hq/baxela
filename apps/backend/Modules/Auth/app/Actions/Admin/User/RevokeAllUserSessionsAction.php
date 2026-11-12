<?php

namespace Modules\Auth\Actions\Admin\User;

use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Support\TwoFactor\TwoFactorChallengeService;
use Modules\Core\Contracts\Events\Auth\UserSessionsRevokedEvent;
use Modules\Core\Utils\Auth;

class RevokeAllUserSessionsAction extends AbstractUserAction
{
    public function __construct(
        User $model,
        protected TwoFactorChallengeService $challenges,
    )
    {
        parent::__construct($model);
    }

    /**
     * Force-revoke every session of the target user (offboarding /
     * suspected compromise). Also invalidates the user's in-flight 2FA
     * challenges (D9) — a forced lockout must not leave a half-
     * authenticated challenge alive. Runs under the user-row lock (D17
     * order: user first, then challenge rows); the audit event fires
     * after commit.
     */
    public function handle(string $id): int
    {
        [$user, $count] = DB::transaction(function () use ($id): array {
            $user = $this->model->query()->lockForUpdate()->findOrFail($id);

            $count = $user->tokens()->delete();

            $this->challenges->invalidateAllForUser($user->id);

            return [$user, $count];
        });

        event(new UserSessionsRevokedEvent(
            id: $user->id,
            email: $user->{UserSchema::EMAIL},
            revoked_count: $count,
            revoked_by: Auth::id(),
        ));

        return $count;
    }
}
