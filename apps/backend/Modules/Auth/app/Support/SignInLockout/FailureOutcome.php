<?php

namespace Modules\Auth\Support\SignInLockout;

/**
 * Result of the row-locked lockout operations (S.4). `LockedWhileInFlight`
 * means the account became (or already was) locked between the caller's
 * pre-check and this operation — the caller must stop the sign-in flow
 * with AUTH_SIGN_IN_LOCKED and never advance toward a challenge or a
 * token (D2e/D2f/D2h).
 */
enum FailureOutcome
{
    case Recorded;

    case Cleared;

    case LockedWhileInFlight;
}
