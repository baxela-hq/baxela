<?php

namespace Modules\Auth\Actions\Public\Auth\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Exceptions\OtpTooManyRequestsException;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Utils\Utility;
use Modules\Core\Contracts\Events\Auth\OtpRequestedEvent;
use Modules\Core\Contracts\Events\Auth\UserSignedUpEvent;
use Random\RandomException;
use Throwable;

/**
 * Shared activation-OTP lifecycle for the email/password signup and the
 * Google sign-in third-party path. Persistence and dispatch are separate
 * members so each caller can apply its own dispatch policy:
 *
 * - persistActivationOtp(): shared, fatal — safe inside a transaction.
 *   Deliberately mirrors the original SignUpAction sequence (throttle
 *   check → generate → store) WITHOUT a user lookup: signup persists the
 *   OTP before the user row exists, so a lookup here would break that
 *   contract. The resend path (AbstractAction::requestOtp → mintOtp)
 *   keeps its own lookup + anti-enumeration.
 * - dispatchActivationEvents(): plain `event()` calls, exceptions
 *   propagate — exactly the SignUpAction contract.
 * - dispatchActivationEventsBestEffort(): Google-only — registers an
 *   after-commit closure INSIDE the transaction (the commit boundary
 *   controls dispatch; a rollback never dispatches) and isolates each
 *   event in its own try/catch: one event's failure never suppresses the
 *   other, and a dispatch failure after a successful commit never turns
 *   the committed authentication into an HTTP failure. Failures are
 *   logged with diagnostic context, never credentials or tokens.
 */
trait IssuesActivationOtp
{
    /**
     * Mint + persist the VERIFY_EMAIL OTP. Fatal on failure (throttle,
     * storage); callers own the ordering relative to user creation.
     *
     * @throws \Random\RandomException
     * @throws \Modules\Auth\Exceptions\OtpTooManyRequestsException
     */
    protected function persistActivationOtp(string $email): string
    {
        $this->errorIfOtpAlreadyActive(OtpCodeTypeEnum::EMAIL, $email, OtpCodeActionEnum::VERIFY_EMAIL);

        $otpCode = Utility::generateOtpCode(self::OTP_LENGTH);

        $this->storeOtp(OtpCodeTypeEnum::EMAIL, $email, $otpCode, OtpCodeActionEnum::VERIFY_EMAIL);

        return $otpCode;
    }

    /**
     * Ordinary signup dispatch: immediate, exceptions propagate — the
     * historical SignUpAction contract, unchanged.
     */
    protected function dispatchActivationEvents(User $user, string $email, string $otpCode): void
    {
        event(new UserSignedUpEvent(
            $user->{UserSchema::ID},
            $user->{UserSchema::EMAIL},
            $user->{UserSchema::CREATED_AT},
        ));

        event(OtpRequestedEvent::fill([
            'email' => $email,
            'code' => $otpCode,
            'action' => OtpCodeActionEnum::VERIFY_EMAIL->value,
        ]));
    }

    /**
     * Google-only dispatch: the events fire after the OUTERMOST transaction
     * commits, and each attempt is isolated.
     */
    protected function dispatchActivationEventsBestEffort(User $user, string $email, string $otpCode): void
    {
        DB::afterCommit(function () use ($user, $email, $otpCode): void {
            try {
                event(new UserSignedUpEvent(
                    $user->{UserSchema::ID},
                    $user->{UserSchema::EMAIL},
                    $user->{UserSchema::CREATED_AT},
                ));
            } catch (Throwable $e) {
                $this->logDispatchFailure('user_signed_up', $user, $e);
            }

            try {
                event(OtpRequestedEvent::fill([
                    'email' => $email,
                    'code' => $otpCode,
                    'action' => OtpCodeActionEnum::VERIFY_EMAIL->value,
                ]));
            } catch (Throwable $e) {
                $this->logDispatchFailure('otp_requested', $user, $e);
            }
        });
    }

    /**
     * Google-only resend dispatch for an existing unverified account: the
     * activation resend carries ONLY the OtpRequestedEvent (no
     * UserSignedUpEvent — the account already exists), attempted best-effort
     * after the caller's persistence succeeded. There is no transaction on
     * this path, so afterCommit executes immediately after the OTP write.
     */
    protected function dispatchOtpRequestedBestEffort(string $email, string $otpCode): void
    {
        DB::afterCommit(function () use ($email, $otpCode): void {
            try {
                event(OtpRequestedEvent::fill([
                    'email' => $email,
                    'code' => $otpCode,
                    'action' => OtpCodeActionEnum::VERIFY_EMAIL->value,
                ]));
            } catch (Throwable $e) {
                $this->logDispatchFailure('otp_requested', null, $e);
            }
        });
    }

    /** Diagnostic context for a swallowed post-commit dispatch failure. */
    protected function logDispatchFailure(string $event, ?User $user, Throwable $e): void
    {
        Log::warning('Google sign-in: post-commit event dispatch failed', [
            'event' => $event,
            'stage' => 'after_commit_dispatch',
            'user_id' => $user?->getKey(),
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
