<?php

namespace Modules\Auth\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;
use Modules\Auth\Models\Role;
use Modules\Auth\Schemas\User\UserSchema;

class UserRequest extends FormRequest
{
    /** @return array<string, list<string|Unique|Exists|Password>> */
    public function rules(): array
    {
        $id = $this->route('id');
        // Case-insensitive uniqueness (postgres-migration DD2): parity with the
        // functional LOWER() unique index on every engine.
        $unique = Rule::unique(UserSchema::TABLE, UserSchema::EMAIL)
            ->where(fn ($query) => $query->whereRaw('LOWER('.UserSchema::EMAIL.') = ?', [mb_strtolower(trim((string) request()->input(UserSchema::EMAIL)))]));

        return [
            UserSchema::PASSWORD => [$id ? 'nullable' : 'required', Password::default(), 'max:40'],
            UserSchema::EMAIL => ['required', 'email', 'max:255',
                $id ? $unique->ignore($id) : $unique],
            UserSchema::IS_ACTIVE => ['required', 'boolean'],
            UserSchema::COMMENT => ['nullable', 'string', 'max:255'],
            UserSchema::ROLE_IDS => ['nullable', 'array'],
            UserSchema::ROLE_IDS.'.*' => ['integer', Rule::exists(Role::class, 'id')],
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
