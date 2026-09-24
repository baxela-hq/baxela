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
use Modules\Auth\Schemas\Token\PersonalAccessTokenSchema;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use Random\RandomException;

class SignInAction extends AbstractAction
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
        // Attempt to authenticate the user; only email/password may reach the
        // guard — extra payload (device_name, remember) would become WHERE clauses.
        $credentials = [
            OtpCodeSchema::EMAIL => $request->{OtpCodeSchema::EMAIL},
            UserSchema::PASSWORD => $request->{UserSchema::PASSWORD},
        ];

        if (! $auth->attempt($credentials)) {
            throw new InvalidCredentialsException;
        }

        $this->errorIfUserNotVerified(OtpCodeTypeEnum::EMAIL, $request->{OtpCodeSchema::EMAIL});
        $this->errorIfUserNotActivated(OtpCodeTypeEnum::EMAIL, $request->{OtpCodeSchema::EMAIL});

        /* @var User $user */
        $user = $auth->user();

        $deviceName = $this->deviceName($request);

        // Replace only this device's previous token; tokens issued to other
        // devices stay valid so concurrent sessions keep working.
        $user->tokens()
            ->where(PersonalAccessTokenSchema::NAME, $deviceName)
            ->delete();

        $token = $user->createToken(
            $deviceName,
            ['*'],
            now()->addDays($this->tokenTtlDays($request)),
        )->plainTextToken;

        // Guest cart token (X-Cart-Token header) — forwarded only when it is
        // a well-formed UUID so the Cart module can merge the guest cart.
        $cartToken = $request->header('X-Cart-Token');
        $cartToken = is_string($cartToken) && Str::isUuid($cartToken) ? $cartToken : null;

        event(new UserSignedInEvent(
            $user->{UserSchema::ID},
            $user->{UserSchema::EMAIL},
            now()->toDateTimeString(),
            $cartToken,
        ));

        return (object) [
            'user' => $user,
            'token' => $token,
        ];
    }

    private function deviceName(SignInRequest $request): string
    {
        $deviceName = $request->validated(UserSchema::DEVICE_NAME);

        if (is_string($deviceName) && $deviceName !== '') {
            return $deviceName;
        }

        // Clients that do not send a stable device id fall back to the
        // user agent, collapsed and truncated to fit the name column.
        $agent = trim((string) preg_replace('/\s+/', ' ', (string) $request->userAgent()));

        return Str::limit($agent, 100, '') ?: 'unknown-device';
    }

    private function tokenTtlDays(SignInRequest $request): int
    {
        // Absent `remember` keeps the long TTL so API clients (admin, Bruno)
        // behave as before; only an explicit false shortens the session.
        $remembered = filter_var($request->validated(UserSchema::REMEMBER) ?? true, FILTER_VALIDATE_BOOLEAN);

        return (int) config('auth.token_ttl.'.($remembered ? 'days' : 'short_days'));
    }
}
