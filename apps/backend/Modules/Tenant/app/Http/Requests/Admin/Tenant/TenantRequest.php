<?php

namespace Modules\Tenant\Http\Requests\Admin\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tenant\Schemas\Tenant\TenantSchema;

/**
 * Create and update share this request. The slug is the store's subdomain
 * identity: required on create, immutable afterwards (accepted on update
 * for the fixed payload shape, never applied).
 */
class TenantRequest extends FormRequest
{
    /** @return array<string, array<int, string|Rule>> */
    public function rules(): array
    {
        // null on create, the tenant id on update
        $id = $this->route('id');

        return [
            TenantSchema::NAME => ['required', 'string', 'max:255'],
            TenantSchema::EMAIL => ['required', 'email', 'max:255'],
            TenantSchema::SLUG => [
                $id === null ? 'required' : 'nullable',
                'string',
                'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/',
                Rule::unique(TenantSchema::TABLE, TenantSchema::SLUG)->ignore($id),
            ],
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
