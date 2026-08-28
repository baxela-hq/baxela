<?php

namespace Modules\Auth\Http\Requests\Public\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\User\UserSchema;

class VerifyAccountActivationOtpRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            OtpCodeSchema::EMAIL => ['required', 'min:8', 'max:255', 'email', 'exists:'.UserSchema::TABLE],
            OtpCodeSchema::CODE => ['required', 'string', 'size:6'],
        ];
    }
}
