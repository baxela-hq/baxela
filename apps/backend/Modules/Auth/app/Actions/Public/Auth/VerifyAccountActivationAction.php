<?php

namespace Modules\Auth\Actions\Public\Auth;

use Modules\Auth\Exceptions\InvalidOtpException;
use Modules\Auth\Http\Requests\Public\Auth\VerifyAccountActivationOtpRequest;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;

class VerifyAccountActivationAction extends AbstractAction
{
    /**
     * @throws InvalidOtpException
     */
    public function handle(VerifyAccountActivationOtpRequest $request): void
    {
        $this->verifyOtp(
            OtpCodeTypeEnum::EMAIL,
            $request->{OtpCodeSchema::EMAIL},
            $request->{OtpCodeSchema::CODE},
            OtpCodeActionEnum::VERIFY_EMAIL
        );
    }
}
