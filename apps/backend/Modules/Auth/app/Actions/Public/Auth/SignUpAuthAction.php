<?php

namespace Modules\Auth\Actions\Public\Auth;

use Modules\Auth\Exceptions\OtpTooManyRequestsException;
use Modules\Auth\Http\Requests\Public\Auth\SignUpRequest;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Utils\Utility;
use Modules\Core\Contracts\Events\Auth\OtpRequestedEvent;
use Modules\Core\Contracts\Events\Auth\UserSignedUpEvent;
use Random\RandomException;

class SignUpAuthAction extends AbstractAuthAction
{
    /**
     * @throws RandomException
     * @throws OtpTooManyRequestsException
     */
    public function handle(SignUpRequest $request): void
    {
        $email = $request->{OtpCodeSchema::EMAIL};

        $this->errorIfOtpAlreadyActive(OtpCodeTypeEnum::EMAIL, $email, OtpCodeActionEnum::VERIFY_EMAIL);

        $otpCode = Utility::generateOtpCode(self::OTP_LENGTH);

        $this->storeOtp(OtpCodeTypeEnum::EMAIL, $email, $otpCode, OtpCodeActionEnum::VERIFY_EMAIL);

        $user = User::query()->create($request->validated());

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
}
