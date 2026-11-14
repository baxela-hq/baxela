<?php

namespace Modules\Auth\Actions\Public\Auth\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Auth\Schemas\User\UserSchema;

/**
 * The sign-in context (device name, remember flag, cart token) shared by
 * the password sign-in and the Google sign-in. Extracted verbatim from
 * SignInAction so both paths build the identical context — device
 * naming/fallbacks, remember defaulting and cart-token validation are
 * unchanged (covered by the existing sign-in regression suites).
 */
trait BuildsSignInContext
{
    /**
     * @return array{device_name: string, remember: bool, cart_token: string|null}
     */
    protected function buildSignInContext(FormRequest $request): array
    {
        return [
            'device_name' => $this->deviceName($request),
            'remember' => $this->remembered($request),
            'cart_token' => $this->cartToken($request),
        ];
    }

    protected function deviceName(FormRequest $request): string
    {
        $deviceName = $request->validated(UserSchema::DEVICE_NAME);

        if (is_string($deviceName) && $deviceName !== '') {
            return Str::limit($deviceName, 100, '');
        }

        $agent = trim((string) preg_replace('/\s+/', ' ', (string) $request->userAgent()));

        return Str::limit($agent, 100, '') ?: 'unknown-device';
    }

    protected function remembered(FormRequest $request): bool
    {
        return filter_var($request->validated(UserSchema::REMEMBER) ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    protected function cartToken(Request $request): ?string
    {
        $cartToken = $request->header('X-Cart-Token');

        return is_string($cartToken) && Str::isUuid($cartToken) ? $cartToken : null;
    }
}
