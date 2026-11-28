<?php

namespace Modules\Tenant\Http\Requests\Admin\Membership;

use Illuminate\Foundation\Http\FormRequest;

class MemberRolesRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['integer'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
