<?php

namespace Modules\Auth\Http\Requests\Admin\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Auth\Models\Permission;
use Modules\Auth\Schemas\Role\RoleSchema;
use Modules\Core\Rules\ScopedUnique;

class RoleRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $id = $this->route('id');

        // Role uniqueness is the spatie team key (multi-tenancy §5): one
        // name per store, platform (NULL-team) rows live outside every
        // store's scope. The scoped rule adds the context tenant — a
        // bare Rule::unique would refuse the same name in another store.
        return [
            RoleSchema::NAME => ['required', 'string', 'min:2', 'max:64', 'alpha_dash',
                new ScopedUnique(RoleSchema::TABLE, RoleSchema::NAME, $id !== null ? (int) $id : null)],
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
