<?php

namespace Modules\Auth\Actions\Public\Auth;

use Modules\Auth\Exceptions\AccountAlreadyActivatedException;
use Modules\Auth\Exceptions\AccountNotActivatedException;
use Modules\Auth\Exceptions\InvalidOtpException;
use Modules\Auth\Exceptions\OtpTooManyRequestsException;
use Modules\Auth\Models\OtpCode;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Utils\Utility;
use Modules\Core\Contracts\Events\Auth\OtpRequestedEvent;
use Modules\Core\Contracts\Events\Auth\UserEmailVerifiedEvent;
use Random\RandomException;

abstract class AbstractAuthAction
{
    protected const int OTP_LENGTH = 6;

    /**
     * @throws RandomException
     * @throws AccountAlreadyActivatedException
     * @throws OtpTooManyRequestsException
     */
    protected function requestOtp(OtpCodeTypeEnum $type, string $value, OtpCodeActionEnum $action): void
    {
        $this->errorIfUserAlreadyVerified($type, $value);

        $this->errorIfOtpAlreadyActive($type, $value, $action);

        $otpCode = Utility::generateOtpCode(self::OTP_LENGTH);

        $this->storeOtp($type, $value, $otpCode, $action);

        event(OtpRequestedEvent::fill([
            'email' => $value,
            'code' => $otpCode,
            'action' => $action->value,
        ]));
    }

    /**
     * @throws InvalidOtpException
     */
    protected function verifyOtp(OtpCodeTypeEnum $type, string $fieldValue, string $otpCode, OtpCodeActionEnum $action): void
    {
        $this->errorIfOtpNotValidated($type, $fieldValue, $otpCode, $action);

        //        $verifiedAt = $type === OtpCodeTypeEnum::MOBILE ? UserSchema::MOBILE_VERIFIED_AT :
        //            UserSchema::EMAIL_VERIFIED_AT;

        $user = User::query()->where($type->value, $fieldValue)->firstOrFail();
        $user->markEmailAsVerified();
        $user->markAsActive();

        event(new UserEmailVerifiedEvent(
            $user->{UserSchema::ID},
            $user->{UserSchema::EMAIL},
            $user->{UserSchema::EMAIL_VERIFIED_AT},
        ));
    }

    /**
     * @throws OtpTooManyRequestsException
     */
    protected function errorIfOtpAlreadyActive(OtpCodeTypeEnum $type, string $fieldValue, OtpCodeActionEnum $action): void
    {
        $latestOtp = OtpCode::query()->where($type->value, $fieldValue)
            ->where(OtpCodeSchema::EXPIRES_AT, '>', now())
            ->where(OtpCodeSchema::IS_USED, false)
            ->where(OtpCodeSchema::ACTION, $action)
            ->latest()
            ->first();

        if ($latestOtp && $latestOtp->{OtpCodeSchema::CREATED_AT}->addSeconds(60)->isFuture()) { // Wait 60 seconds
            throw new OtpTooManyRequestsException;
        }
    }

    /**
     * @throws AccountNotActivatedException
     */
    protected function errorIfUserNotActivated(OtpCodeTypeEnum $type, string $fieldValue): void
    {
        $user = User::query()->where($type->value, $fieldValue)->firstOrFail();

        if (! $user->isActive()) {
            throw new AccountNotActivatedException;
        }
    }

    /**
     * @throws AccountNotActivatedException
     */
    protected function errorIfUserNotVerified(OtpCodeTypeEnum $type, string $fieldValue): void
    {
        $user = User::query()->where($type->value, $fieldValue)->firstOrFail();
        $verifiedAt = $type === OtpCodeTypeEnum::MOBILE ? UserSchema::MOBILE_VERIFIED_AT :
            UserSchema::EMAIL_VERIFIED_AT;

        if (is_null($user->{$verifiedAt})) {
            throw new AccountNotActivatedException;
        }
    }

    /**
     * @throws AccountAlreadyActivatedException
     */
    protected function errorIfUserAlreadyVerified(OtpCodeTypeEnum $type, string $fieldValue): void
    {
        $user = User::query()->where($type->value, $fieldValue)->firstOrFail();
        $verifiedAt = $type === OtpCodeTypeEnum::MOBILE ? UserSchema::MOBILE_VERIFIED_AT :
            UserSchema::EMAIL_VERIFIED_AT;

        if (! is_null($user->{$verifiedAt})) {
            throw new AccountAlreadyActivatedException;
        }
    }

    /**
     * @throws InvalidOtpException
     */
    protected function errorIfOtpNotValidated(OtpCodeTypeEnum $type, string $fieldValue, string $otpCode, OtpCodeActionEnum $action): void
    {
        $otpRecord = OtpCode::query()->where($type->value, $fieldValue)
            ->where(OtpCodeSchema::CODE, $otpCode)
            ->where(OtpCodeSchema::ACTION, $action)
            ->where(OtpCodeSchema::IS_USED, false)
            ->where(OtpCodeSchema::EXPIRES_AT, '>', now())
            ->first();

        if (! $otpRecord) {
            throw new InvalidOtpException;
        }
        // Mark OTP as used to prevent replay attacks
        $otpRecord->markAsUsed();
    }

    protected function storeOtp(OtpCodeTypeEnum $type, string $fieldValue, string $otpCode, OtpCodeActionEnum $action): void
    {
        // Invalidate any old, unused OTPs for this number
        OtpCode::query()->where($type->value, $fieldValue)
            ->where(OtpCodeSchema::IS_USED, false)
            ->update([OtpCodeSchema::IS_USED => true]);

        // Store the OTP
        OtpCode::query()->create([
            $type->value => $fieldValue,
            OtpCodeSchema::TYPE => $type,
            OtpCodeSchema::ACTION => $action,
            OtpCodeSchema::CODE => $otpCode,
            OtpCodeSchema::EXPIRES_AT => now()->addMinutes(5), // OTP valid for 5 minutes
        ]);
    }
}
