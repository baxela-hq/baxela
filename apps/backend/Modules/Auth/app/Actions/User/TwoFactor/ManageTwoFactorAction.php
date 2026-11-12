<?php

namespace Modules\Auth\Actions\User\TwoFactor;

use Illuminate\Support\Facades\DB;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Support\TwoFactor\TwoFactorChallengeService;
use Modules\Auth\Support\TwoFactor\TwoFactorService;

/**
 * Self-service 2FA management (S.1 D7): enable, confirm, disable,
 * regenerate recovery codes. All mutations follow D17 (user lock)
 * and the D14 secret lifecycle rules.
 */
class ManageTwoFactorAction
{
    public function __construct(
        protected TwoFactorService $twoFactor,
        protected TwoFactorChallengeService $challenges,
    ) {}

    /**
     * @return array{enrolled: bool, confirmed_at: ?string, recovery_codes_remaining: int}
     */
    public function status(User $user): array
    {
        return [
            'enrolled' => $user->{UserSchema::TWO_FACTOR_CONFIRMED_AT} !== null,
            'confirmed_at' => $user->{UserSchema::TWO_FACTOR_CONFIRMED_AT}?->toIso8601String(),
            'recovery_codes_remaining' => $this->twoFactor->remainingRecoveryCodes($user),
        ];
    }

    /**
     * Step 1: generate a secret + QR. Stores the secret unconfirmed.
     * Calling twice replaces the unconfirmed secret (D14).
     *
     * @return array{secret: string, qr_code_uri: string}
     */
    public function enable(User $user): array
    {
        if ($user->{UserSchema::TWO_FACTOR_CONFIRMED_AT} !== null) {
            throw AuthException::twoFactorAlreadyEnrolled();
        }

        $secret = $this->twoFactor->generateSecret();
        $user->{UserSchema::TWO_FACTOR_SECRET} = $secret;
        $user->save();

        return [
            'secret' => $secret,
            'qr_code_uri' => $this->twoFactor->qrCodeUri($secret, $user->{UserSchema::EMAIL}),
        ];
    }

    /**
     * Step 2: confirm with a valid TOTP code. Sets confirmed_at,
     * generates recovery codes (returned once). The TOTP acceptance
     * updates the replay guard (D8).
     *
     * @return array{recovery_codes: list<string>}
     */
    public function confirm(User $user, string $code): array
    {
        return DB::transaction(function () use ($user, $code): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->{UserSchema::TWO_FACTOR_CONFIRMED_AT} !== null) {
                throw AuthException::twoFactorAlreadyEnrolled();
            }

            if ($locked->{UserSchema::TWO_FACTOR_SECRET} === null) {
                throw AuthException::twoFactorNotEnrolled();
            }

            // Verify the TOTP code — this also updates the replay guard (D8).
            $matchedStep = $this->twoFactor->verifyCode($locked, $code);

            if ($matchedStep === null) {
                throw AuthException::twoFactorInvalidCode();
            }

            // Generate recovery codes.
            $codes = $this->twoFactor->generateRecoveryCodes();
            $locked->fill([
                UserSchema::TWO_FACTOR_RECOVERY_CODES => $this->twoFactor->hashRecoveryCodes($codes),
                UserSchema::TWO_FACTOR_CONFIRMED_AT => now(),
            ]);
            $locked->save();

            return ['recovery_codes' => $codes];
        });
    }

    /**
     * Disable 2FA. Requires a valid TOTP or recovery code (D11).
     * Clears all four columns + invalidates challenges (D14).
     */
    public function disable(User $user, string $code): void
    {
        DB::transaction(function () use ($user, $code): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->{UserSchema::TWO_FACTOR_CONFIRMED_AT} === null) {
                throw AuthException::twoFactorNotEnrolled();
            }

            // Try TOTP first, then recovery code (D5).
            $verified = $this->twoFactor->verifyCode($locked, $code);

            if ($verified === null) {
                $verified = $this->twoFactor->verifyAndConsumeRecoveryCode($locked, $code)
                    ? 'recovery'
                    : null;
            }

            if ($verified === null) {
                throw AuthException::twoFactorInvalidCode();
            }

            $this->twoFactor->clearAll($locked);
            $locked->save();

            // D9: invalidation trigger.
            $this->challenges->invalidateAllForUser($locked->id);
        });
    }

    /**
     * Regenerate recovery codes. Requires a valid code.
     *
     * @return array{recovery_codes: list<string>}
     */
    public function regenerateRecoveryCodes(User $user, string $code): array
    {
        return DB::transaction(function () use ($user, $code): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->{UserSchema::TWO_FACTOR_CONFIRMED_AT} === null) {
                throw AuthException::twoFactorNotEnrolled();
            }

            $verified = $this->twoFactor->verifyCode($locked, $code);

            if ($verified === null) {
                $verified = $this->twoFactor->verifyAndConsumeRecoveryCode($locked, $code)
                    ? 'recovery'
                    : null;
            }

            if ($verified === null) {
                throw AuthException::twoFactorInvalidCode();
            }

            $codes = $this->twoFactor->generateRecoveryCodes();
            $locked->{UserSchema::TWO_FACTOR_RECOVERY_CODES} = $this->twoFactor->hashRecoveryCodes($codes);
            $locked->save();

            return ['recovery_codes' => $codes];
        });
    }
}
