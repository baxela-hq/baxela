<?php

namespace Modules\Tenant\Http\Requests\Admin\Membership;

use Illuminate\Foundation\Http\FormRequest;

class InviteMemberRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
