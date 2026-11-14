<?php

namespace Modules\Auth\Actions\Public\Auth;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Auth\Actions\Public\Auth\Concerns\BuildsSignInContext;
use Modules\Auth\Actions\Public\Auth\Concerns\IssuesActivationOtp;
use Modules\Auth\Exceptions\AccountNotActivatedException;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Http\Requests\Public\Auth\GoogleSignInRequest;
use Modules\Auth\Models\SocialAccount;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;
use Modules\Auth\Schemas\SocialAccount\SocialAccountSchema;
use Modules\Auth\Schemas\SocialAccount\SocialProviderEnum;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Support\Google\GoogleEmailClass;
use Modules\Auth\Support\Google\GoogleIdTokenVerifier;
use Modules\Auth\Support\Google\GoogleIdentity;

/**
 * Google sign-in for storefront customers (admins keep password + TOTP).
 * Pipeline per docs/plans/google-sign-in.md: ① feature flag ② verify the
 * ID token and atomically consume the nonce ③ resolve the identity
 * (provider pair first, email never) ④ eligibility — administrative and
 * email-conflict checks BEFORE any write ⑤ branch per the dispatch
 * matrix, each account-mutating path atomic:
 *
 * - Existing active linked identity → issueToken → best-effort sign-in
 *   events (once).
 * - Existing inactive linked identity → OTP resend only, best-effort,
 *   no sign-in events, no token/device.
 * - New Gmail/Workspace (verified) → transaction { active+verified user +
 *   social row + issueToken } → best-effort sign-in events.
 * - New third-party (either boolean) → transaction { inactive+unverified
 *   user + social row + OTP persist; afterCommit activation events } →
 *   requires_activation. No token, no device, ever, before activation.
 */
class GoogleSignInAction extends AbstractAction
{
    use BuildsSignInContext;
    use IssuesActivationOtp;

    public function __construct(
        protected GoogleIdTokenVerifier $verifier,
        protected CompleteAuthenticationAction $completion,
    ) {}

    /**
     * @throws AuthException on every rejection path
     */
    public function handle(GoogleSignInRequest $request): object
    {
        // ① Feature flag — the frontend flag is presentational only.
        if (config('auth.google.enabled', false) === false) {
            throw AuthException::googleDisabled();
        }

        $clientId = trim((string) config('auth.google.client_id'));

        if ($clientId === '') {
            // Misconfiguration — fail closed with the dependency error.
            throw AuthException::googleUnavailable();
        }

        // ② Cryptographic verification + atomic nonce consumption. The
        // nonce is burned here, before any account work, and is never
        // restored — a rejected attempt still consumed it (invariant 4/5).
        $identity = $this->verifier->verify($request->validated('credential'), $clientId);

        // ③⑤ Resolution and authentication. Unique-violation races are
        // re-resolved once — the DB constraints are the arbiter, and the
        // retry must never misread an arbitrary failure as a conflict.
        try {
            return $this->authenticate($identity, $request);
        } catch (QueryException $e) {
            if (! $this->isDuplicateEntry($e)) {
                throw $e;
            }

            return $this->authenticate($identity, $request);
        }
    }

    private function authenticate(GoogleIdentity $identity, GoogleSignInRequest $request): object
    {
        $context = $this->buildSignInContext($request);

        // ③ Provider-pair lookup first — Google's `sub` is the permanent
        // identity; the email is never the long-term identifier.
        /** @var SocialAccount|null $social */
        $social = SocialAccount::query()
            ->where(SocialAccountSchema::PROVIDER, SocialProviderEnum::GOOGLE->value)
            ->where(SocialAccountSchema::PROVIDER_USER_ID, $identity->sub)
            ->first();

        if ($social !== null) {
            /** @var User $linkedUser */
            $linkedUser = $social->user()->firstOrFail();

            return $this->signInLinked($linkedUser, $identity, $context);
        }

        // ④ No link: an existing account with this email must never be
        // silently attached to the Google identity (no linking in v1).
        /** @var User|null $existing */
        $existing = User::query()->where(UserSchema::EMAIL, $identity->email)->first();

        if ($existing !== null) {
            // Administrative check first — the account is never modified
            // either way, and neither error reveals more than it must.
            $this->errorIfAdministrative($existing);

            throw AuthException::googleEmailExists();
        }

        // ⑤ New identity — the email-authority class decides the branch.
        if ($identity->class !== GoogleEmailClass::THIRD_PARTY) {
            // Gmail/Workspace: Google is the mailbox authority, so a
            // verified claim activates immediately; an unverified one is
            // rejected (never downgraded to the OTP path).
            if ($identity->emailVerified === false) {
                throw AuthException::googleEmailUnverified();
            }

            $result = DB::transaction(function () use ($identity, $context): array {
                $user = $this->createGoogleUser($identity, active: true);
                $this->createSocialAccount($user, $identity);

                // issueToken() inside the caller transaction (its own
                // nested transaction is a savepoint): device registration,
                // prior-token revocation and token creation all commit or
                // roll back with the account (invariant 6).
                return $this->completion->issueToken($user, $context);
            });

            $this->completion->dispatchEventsBestEffort(
                $result['user'],
                $context,
                (bool) $result['device_is_new'],
            );

            return (object) [
                'token' => $result['token'],
                'user' => $result['user'],
            ];
        }

        // Third-party email: Google's claim is not authoritative for the
        // mailbox, so the account starts inactive+unverified REGARDLESS of
        // the claim's value and must pass the OTP activation flow — the
        // OTP proves ownership. No token, no device before activation.
        $outcome = DB::transaction(function () use ($identity): object {
            $user = $this->createGoogleUser($identity, active: false);
            $this->createSocialAccount($user, $identity);

            // Persisted inside this transaction; the afterCommit closure
            // registered below only fires when the OUTERMOST commit lands.
            $otpCode = $this->persistActivationOtp($identity->email);
            $this->dispatchActivationEventsBestEffort($user, $identity->email, $otpCode);

            return (object) [
                'requires_activation' => true,
                'email' => $identity->email,
            ];
        });

        return $outcome;
    }

    /**
     * @param  array{device_name: string, remember: bool, cart_token: string|null}  $context
     */
    private function signInLinked(User $user, GoogleIdentity $identity, array $context): object
    {
        // Eligibility BEFORE any write (invariant 4): administrative
        // accounts never authenticate through Google, and their rows are
        // untouched by a rejection.
        $this->errorIfAdministrative($user);

        if (! $user->isActive()) {
            return $this->activationResend($user);
        }

        $result = $this->completion->issueToken($user, $context);

        $this->completion->dispatchEventsBestEffort(
            $user,
            $context,
            (bool) $result['device_is_new'],
        );

        return (object) [
            'token' => $result['token'],
            'user' => $result['user'],
        ];
    }

    /**
     * Existing inactive linked identity: no second user, no token/device —
     * reuse the existing activation-OTP mechanism (throttle + anti-
     * enumeration inherited). Lockout does NOT gate activation resend (it
     * gates token issuance); a completed activation still cannot obtain a
     * token until the lock expires (invariant 10). No sign-in events.
     */
    private function activationResend(User $user): object
    {
        $email = $user->{UserSchema::EMAIL};

        if ($user->{UserSchema::EMAIL_VERIFIED_AT} !== null) {
            // Verified but deactivated — the same error as the password
            // path; there is nothing an activation OTP would change.
            $this->errorIfUserNotActivated(OtpCodeTypeEnum::EMAIL, $email);
        }

        $otpCode = $this->mintOtp(OtpCodeTypeEnum::EMAIL, $email, OtpCodeActionEnum::VERIFY_EMAIL);

        if ($otpCode === null) {
            // Verified in a concurrent request since the branch opened —
            // nothing to resend; the sign-in attempt is simply retried.
            throw AuthException::googleEmailExists();
        }

        // Persistence has succeeded — the dispatch attempt may not undo or
        // misrepresent it; a dispatch failure never fails the request.
        $this->dispatchOtpRequestedBestEffort($email, $otpCode);

        return (object) [
            'requires_activation' => true,
            'email' => $email,
        ];
    }

    private function errorIfAdministrative(User $user): void
    {
        // Guard-agnostic via the relations: any role or direct permission
        // makes the account administrative. Ordinary customers hold none
        // (roles are assigned only to staff accounts).
        if ($user->roles()->exists() || $user->permissions()->exists()) {
            throw AuthException::googleAdminForbidden();
        }
    }

    private function createGoogleUser(GoogleIdentity $identity, bool $active): User
    {
        return User::query()->create([
            UserSchema::EMAIL => $identity->email,
            UserSchema::EMAIL_VERIFIED_AT => $active ? now() : null,
            // Unknowable by design: social accounts have no password of
            // their own. A future "set password" flow can replace it.
            UserSchema::PASSWORD => Str::random(40),
            UserSchema::IS_ACTIVE => $active,
        ]);
    }

    private function createSocialAccount(User $user, GoogleIdentity $identity): SocialAccount
    {
        return SocialAccount::query()->create([
            SocialAccountSchema::USER_ID => $user->getKey(),
            SocialAccountSchema::PROVIDER => SocialProviderEnum::GOOGLE,
            SocialAccountSchema::PROVIDER_USER_ID => $identity->sub,
        ]);
    }

    /**
     * SQLSTATE 23000 = integrity constraint violation (MySQL duplicate
     * entry, SQLite UNIQUE constraint). Anything else is a genuine
     * failure and must NOT be re-interpreted as a conflict.
     */
    private function isDuplicateEntry(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23000' || $e->getCode() === '23000';
    }
}
