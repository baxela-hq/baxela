<?php

namespace Modules\Auth\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Auth\Schemas\User\UserSchema;

class UserRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('id');
        $unique = Rule::unique(UserSchema::TABLE, UserSchema::EMAIL);

        return [
            UserSchema::PASSWORD => [$id ? 'nullable' : 'required', 'min:8', 'max:40'],
            UserSchema::EMAIL => ['required', 'email', 'max:255',
                $id ? $unique->ignore($id) : $unique],
            UserSchema::IS_ACTIVE => ['required', 'boolean'],
            UserSchema::IS_ADMIN => ['required', 'boolean'],
            UserSchema::COMMENT => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
