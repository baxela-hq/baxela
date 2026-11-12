<?php

namespace Modules\Auth\Actions\Public\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Auth\Exceptions\AccountAlreadyActivatedException;
use Modules\Auth\Exceptions\AccountNotActivatedException;
use Modules\Auth\Exceptions\InvalidCredentialsException;
use Modules\Auth\Http\Requests\Public\Auth\SignInRequest;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;
use Modules\Auth\Schemas\TwoFactorChallenge\TwoFactorChallengeTypeEnum;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Support\TwoFactor\TwoFactorChallengeService;
use Random\RandomException;

class SignInAction extends AbstractAction
{
    public function __construct(
        protected TwoFactorChallengeService $challenges,
    ) {}

    /**
     * @throws RandomException
     * @throws AccountNotActivatedException
     * @throws AccountAlreadyActivatedException
     * @throws InvalidCredentialsException
     */
    public function handle(SignInRequest $request): object
    {
        $auth = Auth::guard(GuardsEnum::USER_SESSION->value);
        $credentials = [
            OtpCodeSchema::EMAIL => $request->{OtpCodeSchema::EMAIL},
            UserSchema::PASSWORD => $request->{UserSchema::PASSWORD},
        ];

        if (! $auth->attempt($credentials)) {
            throw new InvalidCredentialsException;
        }

        $this->errorIfUserNotVerified(OtpCodeTypeEnum::EMAIL, $request->{OtpCodeSchema::EMAIL});
        $this->errorIfUserNotActivated(OtpCodeTypeEnum::EMAIL, $request->{OtpCodeSchema::EMAIL});

        /** @var User $user */
        $user = $auth->user();

        $context = [
            'device_name' => $this->deviceName($request),
            'remember' => $this->remembered($request),
            'cart_token' => $this->cartToken($request),
        ];

        if ($user->{UserSchema::TWO_FACTOR_CONFIRMED_AT} !== null) {
            $challengeToken = $this->challenges->issue(
                $user,
                TwoFactorChallengeTypeEnum::SIGN_IN,
                $context,
            );

            return (object) [
                'two_factor_required' => true,
                'challenge_token' => $challengeToken,
            ];
        }

        if ($this->forcedEnrollment($user)) {
            $challengeToken = $this->challenges->issue(
                $user,
                TwoFactorChallengeTypeEnum::ENROLLMENT,
                $context,
            );

            return (object) [
                'two_factor_enrollment_required' => true,
                'challenge_token' => $challengeToken,
            ];
        }

        $completion = app(CompleteAuthenticationAction::class);
        $result = $completion->issueToken($user, $context);
        $completion->dispatchEvents($user, $context);

        return (object) $result;
    }

    private function forcedEnrollment(User $user): bool
    {
        if (! config('auth.two_factor.force_super_admin', true)) {
            return false;
        }

        return $user->hasRole('super-admin');
    }

    private function deviceName(SignInRequest $request): string
    {
        $deviceName = $request->validated(UserSchema::DEVICE_NAME);

        if (is_string($deviceName) && $deviceName !== '') {
            return Str::limit($deviceName, 100, '');
        }

        $agent = trim((string) preg_replace('/\s+/', ' ', (string) $request->userAgent()));

        return Str::limit($agent, 100, '') ?: 'unknown-device';
    }

    private function remembered(SignInRequest $request): bool
    {
        return filter_var($request->validated(UserSchema::REMEMBER) ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    private function cartToken(SignInRequest $request): ?string
    {
        $cartToken = $request->header('X-Cart-Token');

        return is_string($cartToken) && Str::isUuid($cartToken) ? $cartToken : null;
    }
}
