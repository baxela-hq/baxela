<?php

namespace Modules\Auth\Http\Requests\Public\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Schemas\User\UserSchema;

class SignInRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            UserSchema::EMAIL => ['required', 'min:5', 'max:255', 'email'],
            UserSchema::PASSWORD => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
