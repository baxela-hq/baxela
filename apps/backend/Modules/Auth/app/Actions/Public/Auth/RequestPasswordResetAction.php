<?php

namespace Modules\Auth\Actions\Public\Auth;

use Modules\Auth\Exceptions\OtpTooManyRequestsException;
use Modules\Auth\Http\Requests\Public\Auth\RequestPasswordResetOtpRequest;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Utils\Utility;
use Modules\Core\Contracts\Events\Auth\OtpRequestedEvent;
use Random\RandomException;

class RequestPasswordResetAction extends AbstractAction
{
    /**
     * @throws RandomException
     * @throws OtpTooManyRequestsException
     */
    public function handle(RequestPasswordResetOtpRequest $request): void
    {
        $email = $request->{OtpCodeSchema::EMAIL};

        // Anti-enumeration: unknown and unverified addresses get the same
        // generic success as everyone else — only a real, verified account
        // receives a reset code.
        $user = User::query()->where(OtpCodeTypeEnum::EMAIL->value, $email)->first();

        if ($user === null || is_null($user->{UserSchema::EMAIL_VERIFIED_AT})) {
            return;
        }

        $this->errorIfOtpAlreadyActive(OtpCodeTypeEnum::EMAIL, $email, OtpCodeActionEnum::FORGOT_PASSWORD);

        $otpCode = Utility::generateOtpCode(self::OTP_LENGTH);

        $this->storeOtp(
            OtpCodeTypeEnum::EMAIL,
            $email,
            $otpCode,
            OtpCodeActionEnum::FORGOT_PASSWORD
        );

        event(OtpRequestedEvent::fill([
            'email' => $email,
            'code' => $otpCode,
            'action' => OtpCodeActionEnum::FORGOT_PASSWORD->value,
        ]));
    }
}
