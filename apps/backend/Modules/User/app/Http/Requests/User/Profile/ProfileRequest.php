<?php

namespace Modules\User\Http\Requests\User\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Modules\User\Schemas\Profile\ProfileSchema;

class ProfileRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            ProfileSchema::FIRST_NAME => ['required', 'string', 'max:255'],
            ProfileSchema::LAST_NAME => ['required', 'string', 'max:255'],
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
