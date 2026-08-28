<?php

namespace Modules\Auth\Actions\Public\Auth;

use Illuminate\Validation\ValidationException;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Exceptions\InvalidOtpException;
use Modules\Auth\Http\Requests\Public\Auth\VerifyPasswordResetOtpRequest;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;
use Modules\Auth\Schemas\User\UserSchema;
use Random\RandomException;

class VerifyPasswordResetAuthAction extends AbstractAuthAction
{
    /**
     * @throws AuthException
     * @throws RandomException|ValidationException
     * @throws InvalidOtpException
     */
    public function handle(VerifyPasswordResetOtpRequest $request): void
    {
        $this->errorIfOtpNotValidated(
            OtpCodeTypeEnum::EMAIL,
            $request->{OtpCodeSchema::EMAIL},
            $request->{OtpCodeSchema::CODE},
            OtpCodeActionEnum::FORGOT_PASSWORD
        );

        $user = User::query()->where(OtpCodeTypeEnum::EMAIL->value, $request->{OtpCodeSchema::EMAIL})->firstOrFail();
        $user->update([UserSchema::PASSWORD => $request->{UserSchema::PASSWORD}]);
    }
}
