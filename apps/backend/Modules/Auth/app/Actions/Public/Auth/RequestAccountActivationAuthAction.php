<?php

namespace Modules\Auth\Actions\Public\Auth;

use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Http\Requests\Public\Auth\RequestAccountActivationOtpRequest;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;
use Random\RandomException;

class RequestAccountActivationAuthAction extends AbstractAuthAction
{
    /**
     * @throws AuthException
     * @throws RandomException
     */
    public function handle(RequestAccountActivationOtpRequest $request): void
    {
        $this->requestOtp(OtpCodeTypeEnum::EMAIL, $request->{OtpCodeSchema::EMAIL}, OtpCodeActionEnum::VERIFY_EMAIL);
    }
}
