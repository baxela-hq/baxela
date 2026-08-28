<?php

namespace Modules\Auth\Actions\Public\Auth;

use Illuminate\Support\Facades\Auth;
use Modules\Auth\Exceptions\AccountAlreadyActivatedException;
use Modules\Auth\Exceptions\AccountNotActivatedException;
use Modules\Auth\Exceptions\InvalidCredentialsException;
use Modules\Auth\Http\Requests\Public\Auth\SignInRequest;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use Random\RandomException;

class SignInAuthAction extends AbstractAuthAction
{
    /**
     * @throws RandomException
     * @throws AccountNotActivatedException
     * @throws AccountAlreadyActivatedException
     * @throws InvalidCredentialsException
     */
    public function handle(SignInRequest $request): object
    {
        $auth = Auth::guard(GuardsEnum::USER_SESSION->value);
        // Attempt to authenticate the user
        if (! $auth->attempt($request->validated())) {
            throw new InvalidCredentialsException;
        }

        $this->errorIfUserNotVerified(OtpCodeTypeEnum::EMAIL, $request->{OtpCodeSchema::EMAIL});
        $this->errorIfUserNotActivated(OtpCodeTypeEnum::EMAIL, $request->{OtpCodeSchema::EMAIL});

        /* @var User $user */
        $user = $auth->user();

        // Delete existing tokens for the user (optional, but good for security)
        // This ensures only one active token per user, per device/login
        $user->tokens()->delete();

        // Generate a new token for the logged-in user
        $token = $user->createToken(GuardsEnum::USER->value)->plainTextToken;

        event(new UserSignedInEvent(
            $user->{UserSchema::ID},
            $user->{UserSchema::EMAIL},
            now()->toDateTimeString(),
        ));

        return (object) [
            'user' => $user,
            'token' => $token,
        ];
    }
}
