<?php

namespace Modules\Tenant\Http\Requests\Admin\TenantDomain;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;

class TenantDomainRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            // A lowercase DNS hostname; punycode for anything non-ASCII.
            TenantDomainSchema::DOMAIN => [
                'required',
                'string',
                'max:253',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
            ],
            TenantDomainSchema::IS_PRIMARY => ['nullable', 'boolean'],
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
