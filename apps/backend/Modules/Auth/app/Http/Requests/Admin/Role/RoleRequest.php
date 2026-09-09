<?php

namespace Modules\Auth\Http\Requests\Admin\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Auth\Models\Permission;
use Modules\Auth\Schemas\Role\RoleSchema;

class RoleRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('id');
        $unique = Rule::unique(RoleSchema::TABLE, RoleSchema::NAME);

        return [
            RoleSchema::NAME => ['required', 'string', 'min:2', 'max:64', 'alpha_dash',
                $id ? $unique->ignore($id) : $unique],
            RoleSchema::PERMISSION_IDS => ['nullable', 'array'],
            RoleSchema::PERMISSION_IDS.'.*' => ['integer', Rule::exists(Permission::class, 'id')],
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
