<?php

namespace Modules\Auth\Http\Requests\Public\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Schemas\User\UserSchema;

class SignUpRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            UserSchema::PASSWORD => ['required', 'min:8', 'confirmed'],
            UserSchema::EMAIL => ['required', 'email', 'unique:'.UserSchema::TABLE],
        ];
    }
}
