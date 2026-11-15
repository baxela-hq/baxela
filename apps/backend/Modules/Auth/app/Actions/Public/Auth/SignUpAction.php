<?php

namespace Modules\Auth\Actions\Public\Auth;

use Modules\Auth\Actions\Public\Auth\Concerns\IssuesActivationOtp;
use Modules\Auth\Exceptions\OtpTooManyRequestsException;
use Modules\Auth\Http\Requests\Public\Auth\SignUpRequest;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Random\RandomException;

class SignUpAction extends AbstractAction
{
    use IssuesActivationOtp;

    /**
     * The sequence is unchanged from the pre-extraction original: throttle
     * check → OTP persist → user create → immediate event dispatch (the
     * dispatch exceptions propagate, exactly as before — the Google flow
     * uses the concern's best-effort variant instead).
     *
     * @throws RandomException
     * @throws OtpTooManyRequestsException
     */
    public function handle(SignUpRequest $request): void
    {
        $email = $request->{OtpCodeSchema::EMAIL};

        $otpCode = $this->persistActivationOtp($email);

        $user = User::query()->create($request->validated());

        $this->dispatchActivationEvents($user, $email, $otpCode);
    }
}
