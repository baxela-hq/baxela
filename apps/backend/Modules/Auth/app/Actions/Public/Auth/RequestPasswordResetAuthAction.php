<?php

namespace Modules\Auth\Actions\Public\Auth;

use Modules\Auth\Exceptions\AccountNotActivatedException;
use Modules\Auth\Exceptions\OtpTooManyRequestsException;
use Modules\Auth\Http\Requests\Public\Auth\RequestPasswordResetOtpRequest;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;
use Modules\Auth\Utils\Utility;
use Modules\Core\Contracts\Events\Auth\OtpRequestedEvent;
use Random\RandomException;

class RequestPasswordResetAuthAction extends AbstractAuthAction
{
    /**
     * @throws RandomException
     * @throws AccountNotActivatedException
     * @throws OtpTooManyRequestsException
     */
    public function handle(RequestPasswordResetOtpRequest $request): void
    {
        $email = $request->{OtpCodeSchema::EMAIL};
        $this->errorIfUserNotVerified(OtpCodeTypeEnum::EMAIL, $email);

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
