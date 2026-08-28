<?php

namespace Modules\Auth\Http\Requests\Public\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\User\UserSchema;

class VerifyPasswordResetOtpRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            UserSchema::EMAIL => ['required', 'email', 'exists:'.UserSchema::TABLE],
            OtpCodeSchema::CODE => ['required', 'string', 'size:6'],
            UserSchema::PASSWORD => ['required', 'min:8', 'confirmed'],
        ];
    }
}
